<?php

namespace PrestaShop\PrestaShop\Module\SolrSearch;
use Solarium\Client as SolariumClient;

class Indexer
{
    private $db;
    private $solarium;

    public function __construct(
        DatabaseWrapper $db,
        array $solrConfig
    ) {
        $this->db = $db;
        $this->solarium = new SolariumClient($solrConfig);
    }

    public function index(array $id_products)
    {
        $productsToIndexSQL = 'SELECT
            p.id_product, pl.*
            FROM ps_product p
            INNER JOIN ps_product_lang pl
                ON pl.id_product = p.id_product'
        ;
        if (!empty($id_products)) {
            $productsToIndexSQL .= ' WHERE p.id_product IN ('.implode(',', array_map('intval', $id_products)).')';
        }

        $update = $this->solarium->createUpdate();


        $batchSize = 50;
        $batchPos  = 0;
        $this->db->query($productsToIndexSQL, [], function (array $product) use ($update, $batchSize, &$batchPos) {
            $doc = $update->createDocument();

            $doc->id = implode('-', [
                $product['id_product'],
                $product['id_shop'],
                $product['id_lang']
            ]);

            $doc->id_product    = $product['id_product'];
            $doc->id_shop       = $product['id_shop'];
            $doc->id_lang       = $product['id_lang'];
            $doc->name          = $product['name'];
            $doc->description   = $product['description'];

            $update->addDocument($doc);

            ++$batchPos;
            if ($batchPos >= $batchSize) {
                $update->addCommit();
                $result = $this->solarium->update($update);
                $batchPos = 0;
            }
        });

        $update->addCommit();
        $result = $this->solarium->update($update);

        if ($result->getStatus() !== 0) {
            throw new Exception(
                'Something went wrong while updating the products on the solr server.'
            );
        }
    }
}
