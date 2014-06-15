<?php

/**
 * Hierarchical Checkbox Set Field
 */
class HierarchicalCheckboxSetField extends CheckboxSetField {
	
	protected $childsource;
	protected $childfilter = null;
	protected $childsort = null;
	protected $disableparentswithchildren = false;
	
	function __construct($name, $title = "", $source = array(), $childsource = null , $value = "", $form = null, $childfilter = null) {
		parent::__construct($name, $title, $source, $value, $form);
		
		$this->childsource = $childsource;
		$this->childfilter = $childfilter;
	}
	
	/**
	 * @todo Explain different source data that can be used with this field,
	 * e.g. SQLMap, DataObjectSet or an array.
	 */
	public function Field($properties = array()) {
		Requirements::css(FRAMEWORK_DIR . '/css/CheckboxSetField.css');

		$source = $this->source;
		$values = $this->value;

		// Get values from the join, if available
		if(is_object($this->form)) {
			$record = $this->form->getRecord();
			if(!$values && $record && $record->hasMethod($this->name)) {
				$funcName = $this->name;
				$join = $record->$funcName();
				if($join) {
					foreach($join as $joinItem) {
						$values[] = $joinItem->ID;
					}
				}
			}
		}

		$items = array();
		// Source is not an array
		if(!is_array($source) && !is_a($source, 'SQLMap')) {
			if(is_array($values)) {
				$items = $values;
			} else {
				// Source and values are DataObject sets.
				if($values && is_a($values, 'SS_List')) {
					foreach($values as $object) {
						if(is_a($object, 'DataObject')) {
							$items[] = $object->ID;
						}
					}
				} elseif($values && is_string($values)) {
					$items = explode(',', $values);
					$items = str_replace('{comma}', ',', $items);
				}
			}
		} else {
			// Sometimes we pass a singluar default value thats ! an array && !SS_List
			if($values instanceof SS_List || is_array($values)) {
				$items = $values;
			} else {
				if($values === null) {
					$items = array();
				}
				else {
					$items = explode(',', $values);
					$items = str_replace('{comma}', ',', $items);
				}
			}
		}
			
		$odd = 0;
		$options = array();

		$source = $this->getSourceItems();

		if($source) {
			foreach($source as $value => $item) {
				if($item instanceof DataObject) {
					$value = $item->ID;
					$title = $item->Title;
				} else {
					$title = $item;
				}

				$itemID = $this->getItemHTMLID($value);
				$odd = ($odd + 1) % 2;
				$extraClass = $odd ? 'odd' : 'even';
				$extraClass .= ' val' . preg_replace('/[^a-zA-Z0-9\-\_]/', '_', $value);

				$data = array(
					'ID' => $itemID,
					'Class' => $extraClass,
					'Name' => "{$this->name}[{$value}]",
					'Value' => $value,
					'Title' => $title,
					'isChecked' => in_array($value, $items) || in_array($value, $this->defaultItems),
					'isDisabled' => $this->disabled || in_array($value, $this->disabledItems)
				);

				if($children = $this->getChildOptions($item)){
					$data['HasChildren'] = true;
					$data['ChildOptions'] = $children;
				}

				$options[] = new ArrayData($data);
			}
		}
		$properties = array_merge($properties, array('Options' => new ArrayList($options)));

		return $this->customise($properties)->renderWith($this->getTemplates());
	}

	function getChildOptions($item){
		if(!is_a($item, 'DataObject') || !$this->childsource) {
			return false;
		}
		$output = '';
		$children = $item->{$this->childsource}();
		if($this->childfilter) {
			$children = $children->where($this->childfilter);
		}
		if(!$children->exists()) {
			return false;
		}
		if($this->childsort){
			$children = $children->sort($this->childsort);
		}
		$values = $this->getValueIDs();

		$options = array();
		foreach($children as $item) {
			$title = $item->Title;
			$value = $item->ID;
			$data = array(
				'ID' => $this->getItemHTMLID($item->ID),
				'Class' => '',//$extraClass,
				'Name' => "{$this->name}[{$value}]",
				'Value' => $item->ID,
				'Title' => $title,
				'isChecked' => in_array($item->ID, $values) || in_array($item->ID, $this->defaultItems),
				'isDisabled' => $this->disabled || in_array($value, $this->disabledItems)
			);
			$options[] = new ArrayData($data);
		}
			
		return new ArrayList($options);
	}

	protected function getSourceItems() {
		$source = $this->source;
		if(is_array($source)) {
			unset($source['']);
		}
		if($source == null){
			$source = array();
		}

		return $source;
	}

	protected function getValueIDs() {
		$values = $this->value;

		//get value from record relation, if possible

		if($values && is_a($values, 'SS_List')) {
			$ids = array();
			foreach($values as $object) {
				if(is_a($object, 'DataObject')) {
					$ids[] = $object->ID;
				}
			}
			return $ids;
		}
		if(is_array($values)){
			return $values;
		}
		if(is_string($values)){
			return explode(",", $values);
		}

		return array($values);
	}
	
	protected function getItemHTMLID($id) {
		return $this->ID() . '_' . preg_replace('/[^a-zA-Z0-9]/', '', $id);
	}

	function disableParentsWithChildren($setting = true){
		$this->disableparentswithchildren = $setting;

		return $this;
	}

	function setChildFilter($filter) {
		$this->childfilter = $filter;

		return $this;
	}

	function setChildSort($sort) {
		$this->childsort = $sort;

		return $this;
	}
	
	/**
	 * Save the current value of this CheckboxSetField into a DataObject.
	 * If the field it is saving to is a has_many or many_many relationship,
	 * it is saved by setByIDList(), otherwise it creates a comma separated
	 * list for a standard DB text/varchar field.
	 *
	 * @param DataObject $record The record to save into
	 */
	function saveInto(DataObjectInterface $record) {
		$fieldname = $this->name ;
		//check if dataobject has a field name the same as this->name
		if($fieldname && $record && ($record->has_many($fieldname) || $record->many_many($fieldname))) {
			$idList = array();
			if($this->value) foreach($this->value as $id => $bool) {
			   if($bool){
					$idList[] = $id;
				}
				//TODO: include support for setting $record->has_many($this->childsorce) and many_many($this->childsource) values
			}						
			$record->$fieldname()->setByIDList($idList);
		} elseif($fieldname && $record) {
			if($this->value) {
				$record->$fieldname = $this->dataValue();
			} else {
				$record->$fieldname = '';
			}
		}
	}
	
	/**
	 * Return the HierarchicalCheckboxSetField value as an string 
	 * selected item keys, with sub arrays in square brackets.
	 * 
	 * TODO: would this be better as JSON, or some specific format?
	 * current format: 1,2,3[2,3,4,6],3,5[3,23],4
	 * JSON: 1,2,3,4,5:{3,5,6}
	 * 
	 * @return string
	 */
	function dataValue() {
		if($this->value && is_array($this->value)) {

			return serialize($this->value);
		}

		return '';
	}
	
	/**
	 * Helper function for building values string.
	 */
	protected function subDataValues(array $items){
		$filtered = array();
		foreach($items as $key => $item) {
			if($item && is_array($item)){
				$filtered[] = $key."[".$this->subDataValues($item)."]";
			}elseif($item) {
				$filtered[] = str_replace(",", "{comma}", $item);
			}
		}

		return implode(',', $filtered);
	}
	
	/**
	 * Transforms the source data for this CheckboxSetField
	 * into a comma separated list of values.
	 * 
	 * @return ReadonlyField
	 */
	function performReadonlyTransformation() {
		$values = '';
		$data = array();
		$items = $this->value;
		if($this->source) {
			foreach($this->source as $source) {
				if(is_object($source)) {
					$sourceTitles[$source->ID] = $source->Title;
				}
			}
		}
		if($items) {
			// Items is a DO Set
			if(is_a($items, 'DataObjectSet')) {
				foreach($items as $item) {
					$data[] = $item->Title;
				}
				if($data){
					$values = implode(', ', $data);	
				}
			// Items is an array or single piece of string (including comma seperated string)
			} else {
				if(!is_array($items)) {
					$items = split(' *, *', trim($items));
				}
				foreach($items as $item) {
					if(is_array($item) && isset($item['Title'])) {
						$data[] = $item['Title'];
					}elseif(is_array($item)){
						//TODO: do something with sub-array
					} elseif(is_array($this->source) && !empty($this->source[$item])) {
						$data[] = $this->source[$item];
					} elseif(is_a($this->source, 'ComponentSet')) {
						$data[] = $sourceTitles[$item];
					} else {
						$data[] = $item;
					}
				}
				$values = implode(', ', $data);
			}
		}
		$title = ($this->title) ? $this->title : '';
		$field = new ReadonlyField($this->name, $title, $values);
		$field->setForm($this->form);
		
		return $field;
	}
	
	function ExtraOptions() {
		return FormField::ExtraOptions();
	}
	
}
