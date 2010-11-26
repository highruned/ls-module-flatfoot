<?

class FlatFoot_Module extends Core_ModuleBase {
  public $helper;
  
  protected function createModuleInfo()
  {
    $this->helper = new FlatFoot_Helper(array(
      'debug' => false//Phpr::$config->get('DEV_MODE')
    ));
    
    if(Phpr::$security->getUser()) { // resynctastic if logged in
      $this->helper->sync_templates();
      $this->helper->sync_partials();
      $this->helper->sync_pages();
    }
    
    $CONFIG['TRACE_LOG']['FLATFOOT'] = PATH_APP . '/logs/flatfoot.txt';
    
    return new Core_ModuleInfo(
      "FlatFoot",
      "CMS object database <-> filesystem synchronization",
      "Eric Muyser"
    );
  }
  
  public function listTabs($tabCollection) {
    $tabCollection->tab('flatfoot', 'FlatFoot', 'sync', 20);
  }
  
  public function subscribeEvents() {
    //Backend::$events->addEvent('onDeleteTemplate', $this, 'before_delete_page');
    //Backend::$events->addEvent('onDeletePartial', $this, 'before_delete_page');
    Backend::$events->addEvent('onDeletePage', $this, 'before_delete_page');
    
    Backend::$events->addEvent('cms:onBeforeDisplay', $this, 'before_page_display'); // doesn't take affect until 2nd reload
  }
  
  public function before_delete_template($template) {
    $this->helper->delete_template($template, array('db_delete' => false));
  }
  
  public function before_delete_partial($page) {
    $this->helper->delete_partial($partial, array('db_delete' => false));
  }
  
  public function before_delete_page($page) {
    $this->helper->delete_page($page, array('db_delete' => false));
  }
 
  public function before_page_display($page) {

  }
}
