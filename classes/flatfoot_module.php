<?

class FlatFoot_Module extends Core_ModuleBase {
  public $helper;
  
  protected function createModuleInfo()
  {
    $this->helper = new FlatFoot_Helper(array(
      'debug' => Phpr::$config->get('DEV_MODE')
    ));
    
    if(!Phpr::$config->get('DISABLE_FLATFOOT') && Phpr::$security->getUser()) { // resynctastic if logged in
      $this->helper->sync_templates();
      $this->helper->sync_partials();
      $this->helper->sync_pages();
    }
    
    $CONFIG['TRACE_LOG']['flatfoot'] = PATH_APP . '/logs/flatfoot.txt';
    
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
    Backend::$events->addEvent('cms:onDeleteTemplate', $this, 'before_delete_template');
    Backend::$events->addEvent('cms:onDeletePartial', $this, 'before_delete_partial');
    Backend::$events->addEvent('cms:onDeletePage', $this, 'before_delete_page');
    Backend::$events->addEvent('cms:onBeforeDisplay', $this, 'before_page_display'); // doesn't take affect until 2nd reload
  }
  
  public function before_delete_template($template) {
    $this->helper->delete_template($template, array('db_delete' => false));
  }
  
  public function before_delete_partial($partial) {
    $this->helper->delete_partial($partial, array('db_delete' => false));
  }
  
  public function before_delete_page($page) {
    $this->helper->delete_page($page, array('db_delete' => false));
  }
 
  public function before_page_display($page) {

  }
}
