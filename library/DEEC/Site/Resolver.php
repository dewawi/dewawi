<?php

class DEEC_Site_Resolver
{
    public function resolveByHost($host)
    {
        $host = strtolower(trim((string) $host));

        if ($host === '') {
            return null;
        }

        $shopsTable = new Zend_Db_Table('shop');
        $shops = $shopsTable->fetchAll(array('activated = ?' => 1));

        foreach ($shops as $shop) {
            $shopData = $shop->toArray();
            $shopHost = strtolower((string) parse_url($shopData['url'], PHP_URL_HOST));

            if ($shopHost !== '' && $shopHost === $host) {
        		$shopData['checkoutenabled'] = true;
                $features = $this->detectLegacyFeatures($shopData);

                return new DEEC_Site_Context($shopData, array('host' => $host), $features);
            }
        }

        return null;
    }

    protected function detectLegacyFeatures(array $site)
    {
        $features = array('cms', 'catalog', 'contact');

        if (!empty($site['checkoutenabled'])) {
            $features[] = 'cart';
            $features[] = 'checkout';
        }

        if (!empty($site['inquiryenabled'])) {
            $features[] = 'inquiry';
        }

        return array_values(array_unique($features));
    }
}
