<?php
if ( ! defined( 'ABSPATH' ) && PHP_SAPI !== 'cli' ) { exit; }
class WP_Plugin_Deploy_Deployer {
    private $resolver; private $validator; private $backups; private $store; private $inspector; private $rollback;
    public function __construct($resolver,$validator,$backups,$store,$inspector,$rollback){$this->resolver=$resolver;$this->validator=$validator;$this->backups=$backups;$this->store=$store;$this->inspector=$inspector;$this->rollback=$rollback;}
    public function deploy( array $request ) {
        $source = $this->resolver->resolve($request); if(is_wp_error($source)) return $source;
        $expected = isset($request['plugin_slug']) ? (string)$request['plugin_slug'] : '';
        if ( '' !== $expected && ! WP_Plugin_Deploy_Package_Validator::is_valid_slug( $expected ) ) { return new WP_Error('invalid_plugin_package','Invalid plugin slug.'); }
        $existing = $expected ? $this->inspector->find_by_slug($expected) : null;
        $needs_update = (bool)$existing;
        if ( $needs_update && ! current_user_can('update_plugins') ) return new WP_Error('permission_denied','You cannot update plugins.');
        if ( ! $needs_update && ! current_user_can('install_plugins') ) return new WP_Error('permission_denied','You cannot install plugins.');
        if ( ! empty($request['activate']) && ! current_user_can('activate_plugins') ) return new WP_Error('permission_denied','You cannot activate plugins.');
        require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/plugin.php'; require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
        $tmp = download_url($source['download_url'],60); if(is_wp_error($tmp)) return new WP_Error('download_failed',$tmp->get_error_message());
        $valid = $this->validator->validate($tmp,$expected?:null); if(is_wp_error($valid)){@unlink($tmp); return $valid;}
        $stage = trailingslashit(get_temp_dir()).'wp-plugin-deploy-'.wp_generate_password(10,false,false); wp_mkdir_p($stage);
        $stage_filesystem=WP_Plugin_Deploy_Filesystem::init(get_temp_dir()); if(is_wp_error($stage_filesystem)){@unlink($tmp); $this->cleanup($stage); return $stage_filesystem;}
        $unz = unzip_file($tmp,$stage); @unlink($tmp); if(is_wp_error($unz)){$this->cleanup($stage); return new WP_Error('invalid_archive',$unz->get_error_message());}
        $hint = $expected ?: ( $source['plugin_slug_hint'] ?? '' );
        $detected = $this->detect_plugin($stage,$hint); if(is_wp_error($detected)){$this->cleanup($stage);return $detected;}
        $slug=$detected['slug']; $target=WP_PLUGIN_DIR.'/'.$slug; $existing=$this->inspector->find_by_slug($slug); $backup=null; $replacement_started=false;
        $filesystem=WP_Plugin_Deploy_Filesystem::init(WP_PLUGIN_DIR); if(is_wp_error($filesystem)){$this->cleanup($stage);return $filesystem;}
        global $wp_filesystem;
        if($existing){$backup=$this->backups->create($slug,$target,is_plugin_active($existing['file'])); if(is_wp_error($backup)){$this->cleanup($stage);return $backup;}}
        try {
            if(is_dir($target)){ if(!$wp_filesystem->delete($target,true)) throw new Exception('Unable to remove existing plugin directory.'); $replacement_started=true; }
            $copied=copy_dir($detected['root'],$target); if(is_wp_error($copied)) throw new Exception($copied->get_error_message()); $replacement_started=true;
            wp_clean_plugins_cache(true); $installed=$this->inspector->find_by_slug($slug); if(!$installed) throw new Exception('WordPress could not discover the deployed plugin.');
            if(!empty($request['activate'])){ $act=activate_plugin($installed['file']); if(is_wp_error($act)) throw new Exception($act->get_error_message()); if(!is_plugin_active($installed['file'])) throw new Exception('Plugin activation could not be verified.'); }
            $event=array('plugin_slug'=>$slug,'action'=>$existing?'update':'install','status'=>'success','source_type'=>$source['source_type'],'source_reference'=>$source['source_reference'],'ref'=>$source['ref'],'version'=>$installed['data']['Version']??'','backup_id'=>$backup['id']??null); $this->store->record($event); $this->cleanup($stage); return $event + array('installed'=>true,'active'=>is_plugin_active($installed['file']),'plugin_file'=>$installed['file']);
        } catch (Throwable $e) {
            $this->cleanup($stage); $rolled=false; $rollback_error=null;
            if($replacement_started && $backup){ $rb=$this->rollback->rollback($slug,$backup['id']); if(is_wp_error($rb)){$rollback_error=$rb->get_error_message();} else {$rolled=true;} }
            $event=array('plugin_slug'=>$slug,'action'=>$existing?'update':'install','status'=>$rolled?'rolled_back':'failure','source_type'=>$source['source_type'],'source_reference'=>$source['source_reference'],'error'=>$e->getMessage(),'rolled_back'=>$rolled); $this->store->record($event);
            if($rollback_error) return new WP_Error('rollback_failed',$e->getMessage().' Rollback failed: '.$rollback_error,array('original_error'=>$e->getMessage()));
            return new WP_Error('verification_failed',$e->getMessage(),array('rolled_back'=>$rolled));
        }
    }
    private function detect_plugin($stage,$expected){
        $files=array(); $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage,FilesystemIterator::SKIP_DOTS)); foreach($it as $f){ if($f->isFile() && strtolower($f->getExtension())==='php'){ $head=file_get_contents($f->getPathname(),false,null,0,8192); if(preg_match('/^[ \t\/*#@]*Plugin Name\s*:/mi',$head)) $files[]=$f->getPathname(); } }
        if(count($files)!==1) return new WP_Error(count($files)>1?'ambiguous_plugin_root':'invalid_plugin_package','Package must contain exactly one plugin main file.');
        $main=$files[0]; $root=dirname($main); $slug=$expected?:sanitize_key(basename($root)); if(!WP_Plugin_Deploy_Package_Validator::is_valid_slug($slug)) return new WP_Error('invalid_plugin_package','Unable to determine a valid plugin slug.');
        return array('slug'=>$slug,'root'=>$root,'main'=>basename($main));
    }
    private function cleanup($path){
        if(!is_dir($path))return;
        $filesystem=WP_Plugin_Deploy_Filesystem::init(get_temp_dir());
        if(!is_wp_error($filesystem)){$filesystem->delete($path,true);}
    }
}
