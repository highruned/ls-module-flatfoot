<?

class FlatFoot_Module extends Core_ModuleBase {
  protected function createModuleInfo()
  {
    if(Phpr::$security->getUser()) { // resynctastic if logged in
      $helper = new FlatFoot_Helper(array(
        'debug' => Phpr::$config->get('DEV_MODE')
      ));
      
      $helper->sync_templates();
      $helper->sync_partials();
      $helper->sync_pages();
    }
     
    return new Core_ModuleInfo(
      "FlatFoot",
      "FlatFoot features",
      "Limewheel Creative Inc."
    );
  }
  
  public function listTabs($tabCollection) {
    $tabCollection->tab('flatfoot', 'FlatFoot', 'sync', 20);
  }
  
  public function subscribeEvents()
  {
    // doesn't take affect until 2nd reload
    Backend::$events->addEvent('cms:onBeforeDisplay', $this, 'before_page_display');
  }
 
  public function before_page_display($page)
  {

  }
}
