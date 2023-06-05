<?php


namespace Drupal\dataimporter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Url;

/**
 * Migrate CSV form
 */

class CsvForm extends FormBase{

	protected $msgObj,$filesystem,$database;

	public function __construct()
	{
		$this->msgObj = \Drupal::messenger();
		$this->filesystem = \Drupal::service("file_system");
		$this->database = \Drupal::database();
	}


	/**
	 * {@inheritdoc}
	 */
	public function getFormId(){
		return 'csv_form';
	}

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state){

		//$this->deleteNodes("blog");
		//echo "Deleted"; exit;

		$form['filename'] = [
			'#type' => "managed_file",
			//'#type' => "file",
			"#title" => $this->t("Upload CSV File"),
			//"#required" => TRUE,
			"#description" => "eg., Select Blog CSV file and click on upload to save data in blog content type of Drupal.",
			'#upload_location' => 'public://importdata',
			'#upload_validators' => [
			    'file_validate_extensions' => ['csv'],
			],
			 
			//"#multiple" => true,
			"#accept"=>"text/csv", 
		];
		
		$form['contenttype'] = [
			'#type' => "select",
			'#title' => 'Content Type',
			'#required' => true,
			'#description' => 'Select content type where you want to import the csv data',
			'#options' => [
				''=>'-- Select Content Type --','Blog'=>'Blog','News'=>'News','PressRelease'=>'Press Release',
			],
		];

		$form["#attributes"] = ['enctype' => 'multipart/form-data'];

		
		$form['submit'] = [
			'#type' => "submit",
			"#value" => "Upload",
		];
		

		return $form;
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state){
		
		$form_files = $form_state->getValue('filename');
		$contenttype = $form_state->getValue('contenttype','');
		
		$msgObj = $this->msgObj;
		
		//echo "<pre>"; print_r($_POST); exit;

		foreach($form_files as $form_file)
		{
			if (isset($form_file) && !empty($form_file)) {
			
			  $file = File::load($form_file);
			  $file_path = $file->getFileUri();
			  $path = \Drupal::service('file_url_generator')->generateAbsoluteString($file_path);
              
			  $csv_data = $this->processCSVFile($file_path);

			  //dump($csv_data); exit;
			  
			  $file->delete();
			  
			  $class = __NAMESPACE__ ."\\".$contenttype;
			  if(class_exists($class)){

			  	$clsObj = new $class();
			  	$clsObj->insert($xml);
			  	//call_user_func($class."->insert",$xml);
			  }
			  else
			  {
			  	$msgObj->addError(t($class. " is not available"));
			  }
			  
			}
		}

		$messages = $msgObj->all();

		if(!isset($messages['error']))
		{
			$msgObj->addMessage(t("Data imported successfully !!"));
		}
	}
	protected function processCSVFile($file_path) {
		$csv_data = [];
	
		// Read the CSV file.
		$file = fopen($file_path, 'r');
		if ($file) {
		  while (($row = fgetcsv($file)) !== FALSE) {
			$csv_data[] = $row;
		  }
		  fclose($file);
		}
	
		return $csv_data;
	  }
    /*
	public function getTermId($term="",$vocabulary)
	{
		$tid = 0;

		if(!empty($term) && trim($term)!='')
		{
			$terms = taxonomy_term_load_multiple_by_name($term,$vocabulary);
			
			if(empty($terms))
			{
				$terms = Term::create([
					'name' => $term,
					'vid' => $vocabulary,
				]);
				$terms->save();
				$tid = $terms->id();
			}
			else{
				foreach($terms as $key=>$term)
				{
					$tid = $key;
					break;
				}
			}
		}

		return $tid;
	}
    */
	public function createDirectory($filelink)
	{
		$filelinkArr = explode("/",$filelink);
		$filename = $filelinkArr[count($filelinkArr)-1];

		$searchArr = array("https://www.mahindra.com","https://mahindra.com");
		$replaceArr = array("","");
		$filename = str_replace($searchArr,$replaceArr,$filename);

		$directory = str_replace("/".$filename,"",$filelink);
		$directory = 'public://'.ltrim($directory,"/");

		$filesystem = $this->filesystem;

		if(!$filesystem->prepareDirectory($directory) )
		{

			$filesystem->mkdir($directory,0777,TRUE);
		}
		
		return array($directory,$filename);
	}


	public function deleteNodes($contenttype)
	{
		$nids = \Drupal::entityQuery('node')->condition('type',$contenttype)->execute();
		$nodes =  \Drupal\node\Entity\Node::loadMultiple($nids);

		foreach($nodes as $node)
		{
			$node->delete();
		}
	}
}
