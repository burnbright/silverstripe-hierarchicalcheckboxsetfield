<?php

class HierarchicalCheckboxSetFieldTest extends SapphireTest{

	protected static $fixture_file = 'HierarchicalCheckboxSetFieldTest.yml';

	protected $extraDataObjects = array(
		'HierarchicalCheckboxSetFieldTest_Article'
	);
	
	function testField() {
		$f = new HierarchicalCheckboxSetField("Articles", "Articles",
				HierarchicalCheckboxSetFieldTest_Article::get()
					->filter("ParentID", 0),
				"Children"
			);
		$field = $f->Field();
	}	

	//saving/loading
	function testValue() {
		$f = new HierarchicalCheckboxSetField("Articles");
		$f->setValue("1,2,3[4,5,6]");
		// $f->setValue(array(
		// 	1,
		// 	2 => array(3,4),
		// 	5
		// ));
	}

	//readonly transformation
	function testReadonlyTransformation() {
		$f = new HierarchicalCheckboxSetField(
				"Articles",
				"Articles",
				HierarchicalCheckboxSetFieldTest_Article::get()
					->filter("ParentID", 0)
			);
		$freadonly = $f->performReadonlyTransformation();
	}

}

class HierarchicalCheckboxSetFieldTest_Article extends DataObject implements TestOnly {
	
	private static $db = array(
		'IsChecked' => 'Boolean'
	);

	private static $extensions = array(
		"Hierarchy"
	);
	
}
