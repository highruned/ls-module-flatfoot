<?

class FlatFoot_Sync extends Backend_Controller {
  public function __construct()
  {
    parent::__construct();
    $this->app_module_name = 'FlatFoot';
    $this->app_page = 'sync';
    $this->app_tab = 'flatfoot';
  }
  
  public function index()
  {
    $this->app_page_title = 'Sync';
    
    $helper = new FlatFoot_Helper(array(
      'debug' => true
    ));
    
    $helper->sync_templates();
    $helper->sync_partials();
    $helper->sync_pages();
  }
}
