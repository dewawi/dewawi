"""Exercise actual contact map markup, filters and navigation in Chromium."""
from pathlib import Path
import base64
import importlib.util
import os
import sys
import time


root = Path(__file__).resolve().parents[2]
sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('map_webdriver', root / 'tests/site/accessibility-http.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
driver = module.WebDriver()
origin = sys.argv[2]
checks = 0


def check(script, message):
    global checks
    assert driver.execute('return ' + script), message
    checks += 1


def count(name, expected):
    driver.wait("return document.querySelector('[data-map-count=\"" + name + "\"]')?.textContent==='" + str(expected) + "'", timeout=10)


def filters(values):
    driver.execute("var root=$('[data-contact-map]'),values=arguments[0];Object.keys(values).forEach(function(key){root.find('[name=\"'+key+'\"]').val(values[key]);});root.find('form').trigger('submit');", [values])


try:
    driver.command('POST', '/goog/cdp/execute', {'cmd': 'Network.enable', 'params': {}})
    driver.command('POST', '/goog/cdp/execute', {'cmd': 'Network.setBlockedURLs', 'params': {'urls': ['https://*']}})
    for width in [1280, 390]:
        driver.command('POST', '/goog/cdp/execute', {'cmd': 'Emulation.setDeviceMetricsOverride', 'params': {'width': width, 'height': 900, 'deviceScaleFactor': 1, 'mobile': width == 390}})
        driver.navigate(origin + '/erp/contacts/contact/map?keyword=&role=all&catid=all&tagid=0&country=0')
        count('total', 248)
        count('visible', 243)
        check('innerWidth===' + str(width) + '&&document.documentElement.scrollWidth<=innerWidth', 'Responsive page overflow')
        check("document.querySelector('[data-map-canvas]').clientHeight>=320", 'Map is not usable at viewport size')
        check("document.querySelectorAll('.leaflet-control-zoom').length===1", 'Map initialized more than once')
        check("getComputedStyle(document.querySelector('[data-contact-map-retry]')).display==='none'", 'Retry action is visible after a successful load')
        check("document.querySelector('[data-map-filters]').open===" + ('false' if width == 390 else 'true'), 'Incorrect responsive filter state')
        if width == 390:
            driver.click('[data-map-filters] summary')
        check("[...document.querySelectorAll('.dw-contact-map__filters select')].every(e=>getComputedStyle(e).display!=='none')", 'Mobile filters hidden')
        driver.execute('fixtureMap.setView([0,360],5,{animate:false})')
        count('visible', 1)
        check("(()=>{var markers=[];fixtureMap.eachLayer(function(layer){if(layer instanceof L.Marker)markers.push(layer)});return markers.length===1&&fixtureMap.getBounds().contains(markers[0].getLatLng())})()", 'Wrapped-world viewport returned an invisible marker')
        driver.click('[data-contact-map-fit]')
        count('visible', 243)
        filters({'keyword': 'Chosen Berlin'})
        count('total', 1)
        count('visible', 1)
        driver.click('.leaflet-marker-icon')
        driver.wait("return !!document.querySelector('.leaflet-popup-content a')")
        check("!window.mapXss&&!document.querySelector('.leaflet-popup-content img')&&document.querySelector('.leaflet-popup-content').textContent.includes('<img')", 'Popup XSS content was interpreted as HTML')
        check("document.querySelector('.leaflet-popup-content a').getAttribute('href')==='/erp/contacts/contact/view/id/1'", 'Popup route lost application base path')
        check("document.querySelector('[data-contact-map-list]').href.includes('keyword=Chosen+Berlin')", 'List navigation lost filters')
        driver.click('.leaflet-popup-content a')
        driver.wait("return !!document.querySelector('input[name=name1][readonly]')")
        check("!window.mapXss&&!document.querySelector('[data-action=\"multi-add\"]')&&!document.querySelector('.dw-multiform__fields input:not([readonly]):not([disabled]):not([type=hidden])')", 'Read-only contact card exposes editable rows or XSS')
        driver.command('POST', '/back', {})
        count('visible', 1)
        check("new URLSearchParams(location.search).get('keyword')==='Chosen Berlin'", 'Returning from a contact lost the map filters')
        if width == 390:
            driver.click('[data-map-filters] summary')
        filters({'keyword': 'Synthetic City', 'role': 'partner', 'catid': '10', 'tagid': '2', 'country': 'DE'})
        count('total', 119)
        count('visible', 119)
        filters({'keyword': '12345 Synthetic City', 'role': 'all', 'catid': 'all', 'tagid': '0', 'country': '0'})
        count('total', 243)
        count('visible', 239)
        driver.wait("return !!document.querySelector('.leaflet-marker-icon[title^=\"119\"]')")
        driver.click('.leaflet-marker-icon[title^="119"]')
        driver.wait("return document.querySelectorAll('.dw-contact-map__popup li').length===50")
        driver.click('.dw-contact-map__popup button')
        driver.wait("return document.querySelectorAll('.dw-contact-map__popup li').length===100")
        driver.click('.dw-contact-map__popup button')
        driver.wait("return document.querySelectorAll('.dw-contact-map__popup li').length===119")
        check("getComputedStyle(document.querySelector('.dw-contact-map__popup button')).display==='none'", 'Paging action remains visible after the final contact')
        check("new Set([...document.querySelectorAll('.dw-contact-map__popup a')].map(a=>a.href)).size===119", 'Co-located contacts were lost or duplicated')
        driver.execute('fixtureMap.closePopup();fixtureMap.setView([0,0],8)')
        count('visible', 0)
        count('total', 243)
        driver.click('[data-contact-map-fit]')
        count('visible', 239)

        driver.execute("window.originalMapAjax=$.ajax;$.ajax=function(options){if(options.url.includes('/mapdata'))options.url='/erp/fixture-error';return originalMapAjax.apply(this,arguments);};")
        filters({'keyword': 'Chosen Berlin'})
        driver.wait("return !document.querySelector('[data-contact-map-retry]').hidden")
        check("document.querySelector('[data-map-status]').getAttribute('role')==='alert'", 'Load error is not announced')
        driver.execute('$.ajax=originalMapAjax')
        driver.click('[data-contact-map-retry]')
        count('visible', 1)

        driver.execute("$.ajax=function(options){var success=options.success;if(options.data&&options.data.mode==='summary'&&options.data.keyword==='Chosen Berlin')options.success=function(data){setTimeout(function(){success(data)},1200);};return originalMapAjax.apply(this,arguments);};")
        filters({'keyword': 'Chosen Berlin'})
        time.sleep(.2)
        filters({'keyword': 'NO_MATCH_FIXTURE'})
        count('total', 0)
        time.sleep(1.4)
        count('total', 0)
        check("document.querySelectorAll('.leaflet-marker-icon').length===0", 'Stale filter response restored old locations')
        driver.execute('$.ajax=originalMapAjax')

        driver.execute("window.oldFixtureMap=fixtureMap;window.mapUnloaded=false;oldFixtureMap.on('unload',function(){window.mapUnloaded=true;});$.get('/erp/contacts/contact/map?keyword=Chosen%20Berlin',function(html){var nodes=$($.parseHTML(html,document,false));$('#content').html(nodes.find('[data-contact-map]'));});")
        count('visible', 1)
        check("mapUnloaded&&oldFixtureMap!==fixtureMap&&document.querySelectorAll('[data-contact-map]').length===1&&document.querySelectorAll('.leaflet-control-zoom').length===1", 'AJAX replacement did not destroy/reinitialize the map')
        driver.execute('DewawiContactsMap.init(document);DewawiContactsMap.init(document)')
        check("document.querySelectorAll('.leaflet-control-zoom').length===1", 'Repeated initialization duplicated the map')
        driver.navigate(origin + '/erp/contacts/contact/index?role=partner&tagid=2')
        driver.wait("return !!document.querySelector('[data-contact-map-open]')")
        check("!document.querySelector('[data-contact-map]')&&!document.querySelector('#contactsmap')", 'Legacy map remains in list')
        driver.execute("$('.dw-toolbar [name=keyword]').val('Chosen Berlin').trigger('input')")
        driver.wait("return document.querySelector('[data-contact-map-open]').href.includes('Chosen+Berlin')", timeout=10)
        driver.click('[data-contact-map-open]')
        count('visible', 1)
        check("document.querySelector('.dw-contact-map__filters [name=role]').value==='partner'&&document.querySelector('.dw-contact-map__filters [name=tagid]').value==='2'", 'List-to-map navigation lost filters')
        artifacts = os.environ.get('DEWAWI_MAP_ARTIFACT_DIR')
        if artifacts:
            if not artifacts.startswith('/tmp/dewawi-'):
                raise ValueError('Artifacts must stay under /tmp/dewawi-*')
            destination = Path(artifacts)
            destination.mkdir(exist_ok=True)
            (destination / ('map-' + str(width) + '.png')).write_bytes(base64.b64decode(driver.command('GET', '/screenshot')))
        print('PASS:', width, 'px map filters, counts, clusters, popups, XSS, errors and AJAX/list navigation')
    print('PASS:', checks, 'Chromium assertions plus asynchronous count and paging checks')
except Exception:
    print('Browser diagnostic:', driver.execute("return {url:location.href,status:document.querySelector('[data-map-status]')?.textContent,counts:[...document.querySelectorAll('[data-map-count]')].map(e=>[e.dataset.mapCount,e.textContent]),fields:[...document.querySelectorAll('.dw-contact-map__filters [name]')].map(e=>[e.name,e.value]),popup:document.querySelector('.dw-contact-map__popup')?.textContent,popupItems:document.querySelectorAll('.dw-contact-map__popup li').length,button:document.querySelector('.dw-contact-map__popup button')?.outerHTML,zoom:window.fixtureMap?.getZoom()}"))
    if os.environ.get('DEWAWI_MAP_ARTIFACT_DIR', '').startswith('/tmp/dewawi-'):
        Path(os.environ['DEWAWI_MAP_ARTIFACT_DIR'], 'failure.png').write_bytes(base64.b64decode(driver.command('GET', '/screenshot')))
    raise
finally:
    driver.close()
