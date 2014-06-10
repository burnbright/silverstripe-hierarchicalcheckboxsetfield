# Hierarchical Checkbox Set Field

This field will produce a set of nested checkboxes.

The source can be a `Hierarchy` of dataobjects, or a multi dimensional array.

The values can be a be an array or a string.

## Usage

```php


$field = HierarchicalCheckboxSetField::create("Pages", "Pages", 
	Page::get()
		->filter("ParentID", 0),
	"Children",
	Page::get()
		->filter("ParentID", 0)
		->filter("ShowInMenus", true)
		->map('ID','ID')
		->toArray()
);
$field->setChildSort("Title DESC");
$field->setChildFilter("ShowInSearch = 1");

```