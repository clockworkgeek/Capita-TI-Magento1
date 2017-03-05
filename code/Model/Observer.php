<?php

class Capita_TI_Model_Observer
{

    /**
     * Handle adminhtml_catalog_product_grid_prepare_massaction
     * 
     * @param Varien_Event_Observer $observer
     */
    public function addMassActionToBlock(Varien_Event_Observer $observer)
    {
        /* @var $block Mage_Adminhtml_Block_Catalog_Product_Grid */
        $block = $observer->getBlock();
        $block->getMassactionBlock()->addItem('capita_translate', array(
                'label' => Mage::helper('capita_ti')->__('Translate'),
                'url'   => Mage::getUrl('*/catalog_product/translate')
            ));
    }

    /**
     * Handler for controller_action_layout_render_before_adminhtml_catalog_product_edit
     * 
     * @param Varien_Event_Observer $observer
     */
    public function warnProductInProgress(Varien_Event_Observer $observer)
    {
        /* @var $product Mage_Catalog_Model_Product */
        $product = Mage::registry('product');
        if ($product) {
            $currentLang = Mage::getStoreConfig('general/locale/code', $product->getStoreId());
            /* @var $requests Capita_TI_Model_Resource_Request_Collection */
            $requests = Mage::getResourceModel('capita_ti/request_collection');
            $requests->addProductFilter($product);
            $requests->addRemoteFilter();
            /* @var $request Capita_TI_Model_Request */
            foreach ($requests as $request) {
                // if is global or in a target language
                if (in_array($currentLang, $request->getDestLanguage())) {
                    Mage::app()->getLayout()->getMessagesBlock()->addWarning(
                        Mage::helper('capita_ti')->__('This product is currently being translated.'));
                    // only needs one match
                    break;
                }
            }
        }
    }

    /**
     * Handler for adminhtml_catalog_category_tabs
     * 
     * @param Varien_Event_Observer $observer
     */
    public function warnCategoryInProgress(Varien_Event_Observer $observer)
    {
        /* @var $category Mage_Catalog_Model_Category */
        $category = Mage::registry('category');
        if ($category && !$category->isObjectNew()) {
            $currentLang = Mage::getStoreConfig('general/locale/code', $category->getStoreId());
            /* @var $requests Capita_TI_Model_Resource_Request_Collection */
            $requests = Mage::getResourceModel('capita_ti/request_collection');
            $requests->addCategoryFilter($category);
//             $requests->addRemoteFilter();
            /* @var $request Capita_TI_Model_Request */
            foreach ($requests as $request) {
                // if is global or in a target language
                if (in_array($currentLang, $request->getDestLanguage())) {
                    Mage::app()->getLayout()->getMessagesBlock()->addWarning(
                        Mage::helper('capita_ti')->__('This category is currently being translated.'));
                    // only needs one match
                    break;
                }
            }
        }
    }

    /**
     * Handler for category_prepare_ajax_response
     * 
     * If category AJAX messages field is empty then javascript doesn't remove old messages.
     * Instead, set something benign to replace them.
     * This might upset other extensions which expect messages to stay.
     * 
     * @param Varien_Event_Observer $observer
     */
    public function unwarnCategoryInProgress(Varien_Event_Observer $observer)
    {
        $response = $observer->getResponse();
        if ($response->getMessages() === '') {
            $response->setMessages('<i></i>'); // invisible content
        }
    }

    public function cronRefresh(Mage_Cron_Model_Schedule $schedule)
    {
        /* @var $client Capita_TI_Model_Api_Requests */
        $client = Mage::getModel('capita_ti/api_requests', array(
            'keepalive' => true
        ));
        /* @var $requests Capita_TI_Model_Resource_Request_Collection */
        $requests = Mage::getResourceModel('capita_ti/request_collection');
        $requests->addRemoteFilter();
        foreach ($requests as $request) {
            if ($request->canUpdate()) {
                $client->updateRequest($request);
            }
        }
    }

    public function cronImport(Mage_Cron_Model_Schedule $schedule)
    {
        /* @var $reader Capita_TI_Model_Xliff_Reader */
        $reader = Mage::getSingleton('capita_ti/xliff_reader');
        $reader->addType(Mage::getSingleton('capita_ti/xliff_import_product'));
        $reader->addType(Mage::getSingleton('capita_ti/xliff_import_category'));
        $reader->addType(Mage::getSingleton('capita_ti/xliff_import_block'));
        $reader->addType(Mage::getSingleton('capita_ti/xliff_import_page'));
        $varDir = Mage::getConfig()->getVarDir() . DS;

        /* @var $requests Capita_TI_Model_Resource_Request_Collection */
        $requests = Mage::getModel('capita_ti/request')->getCollection();
        $requests->addImportingFilter();

        /* @var $request Capita_TI_Model_Request */
        foreach ($requests as $request) {
            try {
                /* @var $document Capita_TI_Model_Request_Document */
                foreach ($request->getDocuments() as $document) {
                    if ($document->getStatus() == 'importing') {
                        $filename = $varDir . $document->getLocalName();
                        $reader->import($filename, $document->getLanguage());
                        $document->setStatus('completed')->save();
                    }
                }
                $request->setStatus('completed')->save();
            }
            catch (Exception $e) {
                $request->setStatus('error')->save();
                // Mage_Cron already has a nice exception logging ability, let it handle this
                throw $e;
            }
        }
    }
}
