<?

if(!function_exists('array_merge_recursive_distinct')) {
  function array_merge_recursive_distinct () {
    $arrays = func_get_args();
    $base = array_shift($arrays);
    if(!is_array($base)) $base = empty($base) ? array() : array($base);
    foreach($arrays as $append) {
      if(!is_array($append)) $append = array($append);
      foreach($append as $key => $value) {
        if(!array_key_exists($key, $base) and !is_numeric($key)) {
          $base[$key] = $append[$key];
          continue;
        }
        if(is_array($value) || (isset($base[$key]) && is_array($base[$key]))) {
          $base[$key] = array_merge_recursive_distinct($base[$key], $append[$key]);
        } else if(is_numeric($key)) {
          if(!in_array($value, $base)) $base[] = $value;
        } else {
          $base[$key] = $value;
        }
      }
    }
    return $base;
  }
}

class FlatFoot_Helper {
  private $settings;
  private $storage_dir;
  
  public function __construct($settings = array())
  {
    $this->settings = (object) array_merge_recursive_distinct(array(
      'storage_dir' => 'resources/cms/',
      'debug' => false
    ), $settings);
    
    $this->mkdir($this->settings->storage_dir);
  }
  
  public function sync_templates() {
    foreach(Cms_Template::create()->find_all() as $template) {
      $template->auto_timestamps = false;
      $definition = $template->serialize();
      
      $sanitized_name = preg_replace('#[^a-zA-Z0-9]#', '_', strtolower($template->name));
      $template_path = $this->settings->storage_dir . 'templates/' . $sanitized_name . '.php';
      $template_exists = file_exists($template_path);
      $template_updated = $template_exists ? filemtime($template_path) : 0;
      $template_definition_path = $this->settings->storage_dir . 'templates/' . $sanitized_name . '/definition.inc';
      $template_definition_exists = file_exists($template_definition_path);
      $template_definition_updated = $template_definition_exists ? filemtime($template_definition_path) : 0;
      
      $timezone = new DateTimeZone(Phpr::$config->get('TIMEZONE'));
      
      if($template->updated_at) {
        $updated_at = $template->updated_at;
        $updated_at->assignTimeZone($timezone);
        $db_updated = strtotime($updated_at->toSqlDateTime());
      }
      else {
        $db_updated = 0;
      }

      if($template_exists && $template_updated > $db_updated) {
        if($template_definition_exists) {
          $definition = json_decode(file_get_contents($template_definition_path));

          $template->save($definition);
        }
        
        $template->html_code = file_get_contents($template_path);
        $template->updated_at = new Phpr_DateTime(date('Y-m-d H:i:s', $template_updated));
        $template->save();
        
        if($this->settings->debug)
          echo "Template (file > db) synchronized.<br />";
      }
      else if($db_updated > $template_updated) {
        $this->file_put_contents($template_path, $template->html_code);
        unset($definition['fields']['html_code']);
        
        $this->file_put_contents($template_definition_path, json_encode($definition));
        
        // db content hasn't changed, but re-sync timestamps
        $template_exists = file_exists($template_path);
        $template_updated = $template_exists ? filemtime($template_path) : 0;

        if($template_updated) {
          $template->updated_at = new Phpr_DateTime(date('Y-m-d H:i:s', $template_updated));
          $template->save();
        }
        
        if($this->settings->debug)
          echo "Template (db > file) synchronized.<br />";
      }
    }
  }
  
  public function sync_partials() {
    $partial_list = array();
    $partials_dir = $this->settings->storage_dir . 'partials/';
    
    $this->mkdir($partials_dir);
    
    if(!$d1 = @opendir($partials_dir))
      return;

    while(($path = readdir($d1)) !== false) {
        if($path === '.' || $path === '..')
            continue;
        $path = explode('.', $path);
        
        if(end($path) === 'php') {
          $sanitized_name = implode('.', array_slice($path, 0, -1));
          
          $partial_list[$sanitized_name] = false;
        }
    }

    closedir($d1);
    
    foreach(Cms_Partial::create()->find_all() as $partial) {
      $sanitized_name = str_replace(':', ';', $partial->name);
      
      $partial_list[$sanitized_name] = $partial;
    }
    
    foreach($partial_list as $sanitized_name => $partial) {
      $partial_path = $this->settings->storage_dir . 'partials/' . $sanitized_name . '.php';
      $partial_exists = file_exists($partial_path);
      $partial_updated = $partial_exists ? filemtime($partial_path) : 0;
      $partial_dir = $this->settings->storage_dir . 'partials/' . $sanitized_name . '/';
      $partial_definition_path = $partial_dir . 'definition.inc';
      $partial_definition_exists = file_exists($partial_definition_path);
      $partial_definition_updated = $partial_definition_exists ? filemtime($partial_definition_path) : 0;
      
      if(!$partial) {
        $partial = new Cms_Partial();
        $partial->name = str_replace(';', ':', $sanitized_name);
        $partial->html_code = file_get_contents($partial_path);
      }
      
      $partial->auto_timestamps = false;
      $definition = $partial->serialize();
      

      
      $timezone = new DateTimeZone(Phpr::$config->get('TIMEZONE'));
      
      if($partial->updated_at) {
        $updated_at = $partial->updated_at;
        $updated_at->assignTimeZone($timezone);
        $db_updated = strtotime($updated_at->toSqlDateTime());
      }
      else {
        $db_updated = 0;
      }

      if($partial_exists && $partial_updated > $db_updated) {
        if($partial_definition_exists) {
          $definition = json_decode(file_get_contents($partial_definition_path));
          
          $partial->unserialize($definition);
        }
        
        $content = trim(file_get_contents($partial_path));
        
        if($content && $content !== '-') {
          $partial->html_code = $content;
          $partial->updated_at = new Phpr_DateTime(date('Y-m-d H:i:s', $partial_updated));
          $partial->save();
          
          if($this->settings->debug)
            echo "Partial (file > db) synchronized.<br />";
        }
        else {
          $partial->delete();
          
          $this->rmfile($partial_path);
          $this->rmdir($partial_dir);
        }
        
        if($this->settings->debug)
          echo "Partial deletion.<br />";
      }
      else if($db_updated > $partial_updated) {
        $content = trim($partial->html_code);
        
        if($content && $content !== '-') {
          $this->file_put_contents($partial_path, $content);
          unset($definition['fields']['html_code']);
          
          $this->file_put_contents($partial_definition_path, json_encode($definition));
          
          // db content hasn't changed, but re-sync timestamps
          $partial_updated = filemtime($partial_path);

          $partial->updated_at = new Phpr_DateTime(date('Y-m-d H:i:s', $partial_updated));
          $partial->save();
          
          if($this->settings->debug)
            echo "Partial (db > file) synchronized.<br />";
        }
        else {
          $partial->delete();
          
          $this->rmfile($partial_path);
          $this->rmdir($partial_dir);
          
          if($this->settings->debug)
            echo "Partial deletion.<br />";
        }
      }
    }
  }
  
  public function sync_pages() {
    foreach(Cms_Page::create()->find_all() as $page) {
      $page->auto_timestamps = false;
      $definition = $page->serialize();
      
      $sanitized_name = ($sanitized_name = preg_replace('#[^a-zA-Z0-9]#', '_', strtolower(substr($page->url, 1)))) ? $sanitized_name : 'home';
      $page_dir = $this->settings->storage_dir . 'pages/' . $sanitized_name . '/';
      $page_path = $this->settings->storage_dir . 'pages/' . $sanitized_name . '.php';
      $page_exists = file_exists($page_path);
      $page_updated = $page_exists ? filemtime($page_path) : 0;
      $page_definition_path = $page_dir . 'definition.inc';
      $page_definition_exists = file_exists($page_definition_path);
      $page_definition_updated = $page_definition_exists ? filemtime($page_definition_path) : 0;
      
      $timezone = new DateTimeZone(Phpr::$config->get('TIMEZONE'));
      
      if($page->updated_at) {
        $updated_at = $page->updated_at;
        $updated_at->assignTimeZone($timezone);
        $db_updated = strtotime($updated_at->toSqlDateTime());
      }
      else {
        $db_updated = 0;
      }

      $this->mkdir($page_dir);
      
      if($page_exists && $page_updated > $db_updated) {
        if($page_definition_exists) {
          $definition = array_merge_recursive_distinct($definition, json_decode(file_get_contents($page_definition_path)));
        
          if(file_exists($page_dir . 'post_action.php'))
            $definition['fields']['action_code'] = file_get_contents($page_dir . 'post_action.php');
          
          if(file_exists($page_dir . 'ajax_handlers.php'))
            $definition['fields']['ajax_handlers_code'] = file_get_contents($page_dir . 'ajax_handlers.php');
            
          if(file_exists($page_dir . 'pre_action.php'))
            $definition['fields']['pre_action'] = file_get_contents($page_dir . 'pre_action.php');
          
          if(file_exists($page_dir . 'head.php'))
            $definition['fields']['head'] = file_get_contents($page_dir . 'head.php');
          
          for($i = 1, $l = 6; $i < $l; ++$i) {
            $page_block_name = $definition['fields']['page_block_name_' . $i];
            $page_block_path = $page_dir . 'page_block_' . $i . '.php';
            
            if(file_exists($page_block_path)) {
              $definition['fields']['page_block_name_' . $i] = $page_block_name;
              $definition['fields']['page_block_content_' . $i] = file_get_contents($page_block_path);
            }
          }
          
          $page->unserialize($definition);
        }
        
        // sync timestamps and save
        $page->content = file_get_contents($page_path);
        $page->updated_at = new Phpr_DateTime(date('Y-m-d H:i:s', $page_updated));
        $page->save();
        
        if($this->settings->debug)
          echo "Page (file > db) synchronized.<br />";
      }
      else if($db_updated > $page_updated) {
        $definition = $page->serialize();
        
        $this->file_put_contents($page_path, $page->content);
        unset($definition['fields']['content']);
        
        if($definition['fields']['action_code']) {
          $this->file_put_contents($page_dir . 'post_action.php', $definition['fields']['action_code']);
          unset($definition['fields']['action_code']);
        }
        
        if($definition['fields']['ajax_handlers_code']) {
          $this->file_put_contents($page_dir . 'ajax_handlers.php', $definition['fields']['ajax_handlers_code']);
          unset($definition['fields']['ajax_handlers_code']);
        }
        
        if($definition['fields']['pre_action']) {
          $this->file_put_contents($page_dir . 'pre_action.php', $definition['fields']['pre_action']);
          unset($definition['fields']['pre_action']);
        }
        
        if($definition['fields']['head']) {
          $this->file_put_contents($page_dir . 'head.php', $definition['fields']['head']);
          unset($definition['fields']['head']);
        }
        
        for($i = 1, $l = 6; $i < $l; ++$i) {
          $name = 'page_block_name_' . $i;
          $content = 'page_block_content_' . $i;
          
          if($definition['fields'][$content]) {
            $this->file_put_contents($page_dir . $name . '.php', $definition['fields'][$content]);
            unset($definition['fields'][$content]);
          }
        }
        
        $this->file_put_contents($page_definition_path, json_encode($definition));

        // db content hasn't changed, but re-sync timestamps and save
        $page_exists = file_exists($page_path);
        $page_updated = $page_exists ? filemtime($page_path) : 0;

        if($page_updated) {
          $page->updated_at = new Phpr_DateTime(date('Y-m-d H:i:s', $page_updated));
          $page->save();
        }
        
        if($this->settings->debug)
          echo "Page (db > file) synchronized.<br />";
      }
    }
  }
  
  private function file_put_contents($path, $contents) {
    $dir = dirname($path);
    // incase the parent folder doesn't exist
    $this->mkdir($dir);
    
    file_put_contents($path, $contents);
    chmod($path, 0777);
  }
  
  private function mkdir($dir) {
    $umask = umask(0);
    
    if(!file_exists($dir))
      mkdir($dir, 0777, true);
    
    umask($umask);
  }
  
  /**
   * Recursively delete a directory
   *
   * @param string $dir Directory name
   * @param boolean $remove_root Delete specified top-level directory as well
   */
  private function rmdir($dir, $remove_root = true) {
    if(!$dh = @opendir($dir))
        return;

    while(($obj = readdir($dh)) !== false)
    {
        if($obj == '.' || $obj == '..')
            continue;

        if (!@unlink($dir . '/' . $obj))
            $this->rmdir($dir . '/' . $obj, true);
    }

    closedir($dh);
   
    if($remove_root)
        @rmdir($dir);
   
    return;
  }
  
  private function rmfile($path) {
    if(is_file($path))
      @unlink($path);
  }
}
