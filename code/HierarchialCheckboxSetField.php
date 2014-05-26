<?php
/**
 * Hierarchial Checkbox Set Field
 * 
 */
class HierarchialCheckboxSetField extends CheckboxSetField {
	
	protected $childsource, $childFilter = null;
	
	protected $disableparentswithchildren = false;
	
	function __construct($name, $title = "", $source = array(), $childsource = null , $value = "", $form = null, $childFilter = null) {
		parent::__construct($name, $title, $source, $value, $form);
		
		$this->childsource = $childsource;
		$this->childFilter = $childFilter;
	}
	
	/**
	 * @todo Explain different source data that can be used with this field,
	 * e.g. SQLMap, DataObjectSet or an array.
	 * 
	 * @todo Should use CheckboxField FieldHolder rather than constructing own markup.
	 */
	function Field($properties = array()) {
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
		// Source is not an array
		if(!is_array($source) && !is_a($source, 'SQLMap')) {
			if(is_array($values)) {
				$items = $values;
			} else {
				// Source and values are DataObject sets.
				if($values && is_a($values, 'DataObjectSet')) {
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
			// Sometimes we pass a singluar default value thats ! an array && !DataObjectSet
			if(is_a($values, 'DataObjectSet') || is_array($values)) {
				$items = $values;
			} else {
				$items = explode(',', $values);
				$items = str_replace('{comma}', ',', $items);
			}
		}
		if(is_array($source)) {
			unset($source['']);
		}
		$odd = 0;
		$options = '';
		if ($source == null) {
			$source = array();
			$options = "<li>No options available</li>";
		}
		foreach($source as $index => $item) {
			if(is_a($item, 'DataObject')) {
				$key = $item->ID;
				$value = $item->Title;
			} else {
				$key = $index;
				$value = $item;
			}
			$odd = ($odd + 1) % 2;
			$extraClass = $odd ? 'odd' : 'even';
			$extraClass .= ' val' . str_replace(' ', '', $key);
			$itemID = $this->id() . '_' . preg_replace('/[^a-zA-Z0-9]+/', '', $key);
			$checked = isset($items) && in_array($key, $items) ? ' checked="checked"' : '';
			$disabled = ($this->disabled) ? $disabled = ' disabled="disabled"' : '';
			$subboxes = ($this->childsource) ? $this->getSubBoxes($item) : false;
			$input = $this->disableparentswithchildren && $subboxes ? "" :
				"<input id=\"$itemID\" name=\"$this->name[$key]\" type=\"checkbox\" value=\"$key\"$checked $disabled class=\"checkbox\" />";
			$options .= "<li class=\"$extraClass\">$input <label for=\"$itemID\">$value</label>$subboxes</li>\n"; 
		}
		
		return "<ul id=\"{$this->id()}\" class=\"optionset checkboxsetfield{$this->extraClass()}\">\n$options</ul>\n"; 
	}
	
	/**
	 * Helper method to get the sub boxes using $this->childsource to reference the child DataObject field.
	 */
	function getSubBoxes($item){
		if(!is_a($item, 'DataObject')) {
			return false;
		}
		$key = $item->ID;
		$output = '';

		$children = $item->{$this->childsource}();
		if($this->childFilter) {
			$children = $children->where($this->childFilter);
		}
		if(!$children->exists()) {
			return false;
		}
		foreach($children as $do) {
			$itemID = $this->id() . '_'.$item->ID."_".$do->ID;
			$title = $do->Title;
			$value = $do->ID;
			$name = "$this->name[$key][$value]";
			$disabled = ''; //TODO: allow disabled support
			$checked = in_array($key, $this->value) ? ' checked="checked"' : '';
			$input = "<input id=\"$itemID\" name=\"$name\" type=\"checkbox\" value=\"$value\"$checked $disabled class=\"checkbox\" />";
			$output .= "<li>$input <label for=\"$itemID\">$title</label></li>";
		}
			
		return "<ul>$output</ul>";
	}
	
	function disableParentsWithChildren($setting = true){
		$this->disableparentswithchildren = $setting;
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
	 * Return the HierarchialCheckboxSetField value as an string 
	 * selected item keys, with sub arrays in square brackets.
	 * 
	 * TODO: would this be better as JSON, or some specific format?
	 * 
	 * current format: 1,2,3[2,3,4,6],3,5[3,23],4
	 * 
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
				if($data) $values = implode(', ', $data);
				
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
