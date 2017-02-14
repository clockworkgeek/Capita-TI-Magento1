<?php

class Capita_TI_Block_Adminhtml_Request_New_Form extends Mage_Adminhtml_Block_Widget_Form
{

    protected function _prepareForm()
    {
        $form = new Varien_Data_Form(array('id' => 'edit_form', 'action' => $this->getData('action'), 'method' => 'post'));
        $form->setUseContainer(true);
        $locales = Mage::helper('capita_ti')->getStoreLocalesOptions();

        $general = $form->addFieldset('general', array(
            'legend' => $this->__('General')
        ));
        $general->addField('source_language', 'select', array(
            'name' => 'source_language',
            'label' => $this->__('Source Language'),
            'required' => true,
            'values' => $locales
        ));
        $general->addField('dest_language', 'multiselect', array(
            'name' => 'dest_language',
            'label' => $this->__('Requested Languages'),
            'required' => true,
            'values' => $locales
        ))
        ->setAfterElementHtml('<script type="text/javascript">
            Event.observe("source_language", "change", function(event) {
                $$("#dest_language option").invoke("writeAttribute","disabled",null);
                $$("#dest_language option[value="+$F(this)+"]").invoke("writeAttribute","disabled","disabled");
            });
            $$("#dest_language option[value='.@$locales[0]['value'].']").invoke("writeAttribute","disabled","disabled");
            </script>');

        $products = $form->addFieldset('products', array(
            'legend' => $this->__('Products')
        ));
        $products->addField('product_attributes', 'multiselect', array(
            'name' => 'product_attributes',
            'label' => $this->__('Product Attributes'),
            'required' => true,
            'values' => Mage::helper('capita_ti')->getProductAttributesOptions()
        ));

        $this->setForm($form);
        return parent::_prepareForm();
    }

}
