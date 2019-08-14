<?php
class IMerchandise_Sync_Model_Core_Api extends Mage_Api_Model_Resource_Abstract
{
    
    public function getCurrentRootCategoryID(){
	$catID=Mage::app()->getStore()->getRootCategoryId();
	return (string)$catID;
    }

	public function createConfigAttribute($productSku,$attributeId,$label){
	
		// Load the product
		$product = Mage::getModel('catalog/product')->setStoreId(0);
		$product=Mage::getModel('catalog/product')->loadByAttribute('sku',$productSku);
		
        if (!$product) {
            $this->_fault('configurable_product_does_not_exist');
        }
		
        $attribute = Mage::getModel('catalog/entity_attribute')->setStoreId(0);
        $attribute->load($attributeId);
            
		if ($product && $attribute){
			// Check that the configurable attribute doesn't already exist for the configurable product.
			$product_configurable_attributes = $product->getTypeInstance()->getConfigurableAttributesAsArray();
			foreach($product_configurable_attributes as $attrib){
				if ($attributeId==$attrib["attribute_id"]){
					$this->_fault('configurable_attribute_already_exists');
				}
			}
			$configurableAttribute = Mage::getModel("catalog/product_type_configurable_attribute");
			$configurableAttribute->setProductId($product->getId());
			$configurableAttribute->setAttributeId($attributeId);
	    	$configurableAttribute->setPosition(0);
	    	$configurableAttribute->setStoreId(0);
			$configurableAttribute->setLabel($label);
			$configurableAttribute->save();
    		$configurableAttribute->setValues(null);
    		$configurableAttribute->save();
			return $configurableAttribute->getId();
		}
		return false;
	}
	
	public function assignProductsToConfigurable($configurableProductSku,array $productSkus){
		$configurable_product=Mage::getModel('catalog/product')->loadByAttribute('sku',$configurableProductSku);
        if (!$configurable_product) {
            $this->_fault('configurable_product_does_not_exist');
        }
		
		$children_id_array=Mage::getResourceSingleton('catalog/product_type_configurable')->getChildrenIds($configurable_product->getId());
		
		$childrenIds=$children_id_array[0];
		/**
		foreach ($children_id_array as $key->$value){
			$childrenIds[$value]=$value;
		}
		*/
		$product = Mage::getModel('catalog/product');
		
		
		foreach($productSkus as $sku){
			$productId = $product->getIdBySku($sku);
			if ($productId){
				if(!array_key_exists($productId,$childrenIds)){
					$childrenIds[$productId]=$productId;
				} 						
			}
		}
		$configurable_product->setConfigurableProductsData($childrenIds);
		//$configurable_product->setHasOptions(true);
		$configurable_product->setRequiredOptions(true);
		Mage::log('Product from iMerchandise Sync:');
		Mage::log($configurable_product);
		$configurable_product->save();
		return true;
		
	}
	public function createAttributeOption($attributeId,$option_label){
        $attribute = Mage::getModel('catalog/product')
            ->setStoreId(0)
            ->getResource()
            ->getAttribute($attributeId);
        if (!$attribute->getId()) {
            $this->_fault('attribute_does_not_exist');
        }
		foreach ($attribute->getSource()->getAllOptions(true) as $option){
			if (strcmp($option_label,$option['label']) == 0){
				$this->_fault('attribute_option_already_exists');
			}
		}
		$option=array();
		$option['attribute_id'] = $attributeId; 
		$option['value']['new_option'][0] = $option_label;
		$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
		$setup->addAttributeOption($option);
		// Since the addAtributeOption does not return a value, we will have to parse out the new option by name and return it's id.
		
		$attribute->save();
		
        $collection = Mage::getResourceModel('eav/entity_attribute_option_collection')
            ->addFieldToFilter('attribute_id',$attributeId)
        	->join('attribute_option_value','main_table.option_id=attribute_option_value.option_id')
            ->addFieldToFilter('value',$option_label);
		$new_option = $collection->getFirstItem();
		
		return $new_option->getId();
	}
	
	
    private function getProductEntityTypeId()
    {
        return Mage::getModel('catalog/product')->getResource()->getEntityType();
    }

	public function createAssignAttribute($code, $label){
		$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
		$data=array(
		             'label'             => $label,
		             'type'              => 'varchar',
		             'input'             => 'select',
		             'backend'           => 'eav/entity_attribute_backend_array',
		             'frontend'          => '',
		             'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
		             'visible'           => true,
		             'required'          => true,
		             'user_defined'      => true,
		             'searchable'        => false,
		             'filterable'        => false,
		             'comparable'        => false,
		             'option'            => array (
		                                            'value' => array()
		                                        ),
		             'visible_on_front'  => false,
		             'visible_in_advanced_search' => false,
		             'unique'            => false,
		             'configurable' =>true
		);
		$model = $setup->addAttribute('catalog_product', $code,$data);
		$attribute=$setup->getAttribute('catalog_product', $code);
		return $attribute['attribute_id'];

	}
	
	public function skuExists($sku){
	    $product=Mage::getModel('catalog/product')->loadByAttribute('sku',$sku);
	    if ($product)
	    {
		return true;
	    }
	    return false;
	}
	
	
}
?>
