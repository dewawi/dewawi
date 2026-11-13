"""HTTP regressions run only inside the existing disposable MariaDB fixture runner."""
import json
import subprocess


def check_variant_http(work, root, env, request, state, Inputs, check):
    path = '/?id=910'
    before = state()
    for family, variant_id, option_id in [('equipment', '2001', 204), ('trading', '2101', 214)]:
        status, html = request(path)
        check(status == 200 and 'BASE-2000' in html and 'BASE-2100' in html, 'Public base choices missing')
        tokens = {key: Inputs(html).values[key] for key in ['csrf_token', 'inquiry_token']}

        def post(values):
            return request(path, dict(tokens, **values))

        status, body = post({'step': 'base', 'family': '2200'})
        check(status == 200 and json.loads(body)['status'] == 'error', 'Unmapped base ID accepted')
        status, body = post({'step': 'base', 'family': family})
        configuration_html = json.loads(body)['formHtml']
        check(status == 200 and 'data-current-step="configuration"' in configuration_html and ('Fixture option ' + str(201 if family == 'equipment' else 211)) in configuration_html, 'Family-specific controls missing')
        config = {'step': 'configuration', 'variantid': variant_id, 'execution': 'small', 'kit': 'yes', 'quantity': '2,5'}
        check(post(dict(config, variantid='999999'))[0] == 400, 'HTTP accepted manipulated variant ID')
        status, body = post(dict(config, kit='204'))
        check(status == 200 and json.loads(body)['status'] == 'error', 'HTTP trusted an option ID instead of configured value')
        status, body = post(dict(config, preview='1'))
        preview = json.loads(body)['formHtml']
        check(status == 200 and 'data-current-step="configuration"' in preview and 'variant-pricing' in preview, 'Same-step AJAX price preview failed: ' + body)
        check(Inputs(preview).values['variantid'] == variant_id, 'Preview lost canonical variant identity')
        if family == 'equipment':
            check('kostenlose Ausstattung' in preview and 'Im Variantenpreis enthalten' in preview and 'Zusätzlich' in preview, 'Included/free/extra labels missing')
        (work / ('variant-' + family + '.html')).write_text(preview)
        check(len(state()['variant_inquiries']) == len(before['variant_inquiries']), 'Preview saved an inquiry')
        status, body = post(config)
        contact_html = json.loads(body)['formHtml']
        check(status == 200 and 'data-current-step="contact"' in contact_html, 'Variant configuration did not advance')
        contact = {'step': 'contact', 'email': 'variant-http@example.test', 'privacy': '1', 'sku': 'FORGED', 'titel': 'Forged title', 'clientid': '200', '_selection': 'forged approval'}
        if family == 'equipment':
            router = root / 'tests/security/fixtures/inquiry-pilot-http.php'
            subprocess.run(['php', str(router), 'variant-retire'], env=env, check=True, capture_output=True)
            check(post(contact)[0] == 400, 'Final save did not revalidate retired variant')
            subprocess.run(['php', str(router), 'variant-price'], env=env, check=True, capture_output=True)
        status, body = post(contact)
        check(status == 200 and 'gespeichert' in json.loads(body)['formHtml'], 'Variant inquiry not saved: ' + body)
        after = state()
        saved = after['variant_inquiries'][-1]
        selection = saved['_selection']
        check(selection['method'] == 'variant_config' and selection['eligible'] is False and selection['technical']['status'] == 'review_required', 'Forged automatic release stored')
        check(selection['selected']['itemid'] == int(variant_id) and saved['sku'] == 'DUPLICATE-VARIANT' and saved['titel'] != 'Forged title', 'Canonical ID/SKU/title not stored')
        check(selection['options'][-1]['itemoptid'] == option_id and selection['options'][-1]['included'] is False, 'Real family-specific option identity not stored')
        if family == 'equipment':
            check(selection['commercial']['positions'][0]['price'] == 1400.125, 'Final save used stale preview price')
        check(after['quotes'] == before['quotes'], 'Variant configuration created a quote')
        count = len(after['variant_inquiries'])
        post(contact)
        check(len(state()['variant_inquiries']) == count, 'Repeated variant submission created duplicates')
        before = state()

    # An earlier base change must discard the configuration and its actual variant ID.
    status, html = request(path)
    tokens = {key: Inputs(html).values[key] for key in ['csrf_token', 'inquiry_token']}
    post({'step': 'base', 'family': 'equipment'})
    post({'step': 'configuration', 'execution': 'small', 'kit': 'yes', 'quantity': '1', 'variantid': '2001'})
    post({'step': 'contact', 'back': '1'})
    post({'step': 'configuration', 'execution': 'small', 'kit': 'yes', 'quantity': '1', 'variantid': '2001', 'back': '1'})
    status, body = post({'step': 'base', 'family': 'trading'})
    changed = json.loads(body)['formHtml']
    check(Inputs(changed).values.get('variantid', '') == '' and 'data-variantid="2001"' not in changed and 'data-variantid="2101"' in changed, 'Changed base retained stale variants/options')
    check(state()['quotes'] == before['quotes'], 'Back navigation created a quote')
    print('PASS: variant HTTP preview, two product families, final revalidation, current prices, canonical storage, base changes and replay')
