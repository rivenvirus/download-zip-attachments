<?php
/*
Plugin Name: Download Zip Attachments
Plugin URI: http://www.sorcode.com
Description: Download all attachments from the post into a zip file
Version: 1
Author: rivenvirus
Author Email: santiago@sorcode.com
License:

  Copyright 2011 rivenvirus (santiago@sorcode.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  
*/

class DownloadZipAttachments extends WP_Widget{

    /*--------------------------------------------*
	 * Constants
	 *--------------------------------------------*/
	const name = 'Download Zip Attachments';
	const slug = 'download_zip_attachments';
	var $post_types = array('post','bodas','ims_gallery');
    var $post_types_attachments = array('attachment','ims_image');
	/**
	 * Constructor
	 */
	function __construct() {		
		register_activation_hook( __FILE__, array( &$this, 'install_download_zip_attachments' ) );
		add_action( 'init', array( &$this, 'init_download_zip_attachments' ) );
        
        $widget_ops = array( 'classname' => 'download_zip_attach_widget', 'description' => __('Coloca un botón de descarga de attachments en el single', 'download_zip_attachments') ); // Widget Settings
        $control_ops = array( 'id_base' => 'download_zip_attach_widget'); // Widget Control Settings
        $this->WP_Widget( 'download_zip_attach_widget', self::name, $widget_ops, $control_ops );   
                
	}
  
    function widget($args,$instance){
        global $post;
        if(is_single()){
        	$title = apply_filters('widget_title', $instance['title']); // the widget title
    		echo $args['before_widget'];	    
    		if ( $title )
    		echo $args['before_title'] . $title . $args['after_title']; 	
    		$this->display_widget($widget);	
    	   echo $args['after_widget'];			
        }
	}  
    
    function display_widget($widget){
    	$this->download_metabox();		
	}
    
    
  	function init_download_zip_attachments() {
		// Setup localization
		load_plugin_textdomain( self::slug, false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
		// Load JavaScript and stylesheets
		$this->register_scripts_and_styles();

		// Register the shortcode [download_zip_attachments]
		add_shortcode('download_zip_attachments', 'render_shortcode');
	
		if ( is_admin() ) {
			//this will run when in the WordPress admin
            add_action( 'add_meta_boxes', array(&$this,'meta_box'));
		} else {

		}
   
        add_action('wp_ajax_nopriv_download_zip_attachments', array( &$this, 'download_zip' ) );
        add_action('wp_ajax_download_zip_attachments', array( &$this, 'download_zip' ) );
        
	}

	private function register_scripts_and_styles() {
		if ( is_admin() ) {			
			$this->load_file( self::slug . '-admin-style', '/css/admin.css' );
		} else {			
			$this->load_file( self::slug . '-style', '/css/widget.css' );
		}   // end if/else
	} // end register_scripts_and_styles
	
	/**
	 * Helper function for registering and enqueueing scripts and styles.
	 *
	 * @name	The 	ID to register with WordPress
	 * @file_path		The path to the actual file
	 * @is_script		Optional argument for if the incoming file_path is a JavaScript source file.
	 */
	private function load_file( $name, $file_path, $is_script = false ) {

		$url = plugins_url($file_path, __FILE__);
		$file = plugin_dir_path(__FILE__) . $file_path;

		if( file_exists( $file ) ) {
			if( $is_script ) {
				wp_register_script( $name, $url, array('jquery') ); //depends on jquery
				wp_enqueue_script( $name );
			} else {
				wp_register_style( $name, $url );
				wp_enqueue_style( $name );
			} // end if
		} // end if

	} // end load_file
    
    function download_zip(){        
        require "create_zip_file.php";
        $files_to_zip = array();// create files array
        //run a query
        $post_id = $_POST["Id"];
        $args = array(
            'post_type' => $this->post_types_attachments,
            //'post_type' => 'attachment',
            //'numberposts' => null,
            //'post_status' => null,
            'post_parent' => $post_id
        );
        
        $attachments = get_posts($args);
        if ($attachments) {
            //print_r($attachments);
            foreach ($attachments as $attachment) {
              $files_to_zip [] = get_attached_file( $attachment->ID ); // populate files array
            }
        
            //print_r($files_to_zip);
            //exit;
            $zip = new CreateZipFile;
            $uploads = wp_upload_dir(); 
            $tmp_location = $uploads['path'];
            $tmp_location_url = $uploads['url'];
            $FileName = sanitize_title(get_the_title($post_id)).".zip";
            $zipFileName = $tmp_location.'/'.$FileName;        
            //$zipFileName_url = $tmp_location_url.'/'.sanitize_title(get_the_title($post_id)).".zip";        
            //$zip->addDirectory('/');
            foreach ($files_to_zip as $file) {                             
                //$filecontent =             
                $zip->addFile(file_get_contents($file), basename($file));
            }
            
            $handle = fopen($zipFileName, 'wb');
            $out = fwrite($handle, $zip->getZippedFile());
            fclose($handle);
            //$zip->forceDownload($zipFileName); 
            //@unlink($zipFileName);    
            echo plugins_url($file_path, __FILE__)."/download.php?File=".$FileName;
        }else{
            echo 'false';
        }
        exit;
    }
  
   function meta_box() {  
        for($i=0; $i<count($this->post_types); $i++){
            add_meta_box( 'download_metabox', __('Descargar Attachments', 'download_zip_attachments'), array(&$this,'download_metabox'), $this->post_types[$i], 'side', 'high' );
        }        
    }
        
    function download_metabox(){ ?>
        <input class="button-primary" type="button" name="DownloadZip" id="DownloadZip" value="<?php echo __('Descargar Zip', 'download_zip_attachments') ?>" onclick="download_zip_attachments_();" />
        <div class="download_zip_loading" style="display:none"></div>
        <script type="text/javascript">
            function download_zip_attachments_(){    
              jQuery.ajax({
                type: 'POST',
                url: "/wp-admin/admin-ajax.php",
                data: { action : 'download_zip_attachments',Id:<?php echo get_the_id(); ?> },
                beforeSend: function(){
                    jQuery('.download_zip_loading').show();
                },
                success: function(data){                  
                 
                  if(data != 'false'){
                    window.location = data;
                  }else{
                    alert('<?php echo __('Este post no contiene attachments', 'download_zip_attachments'); ?>');
                  }
                  jQuery('.download_zip_loading').hide();
                }
              });
            }
        </script>
    <?php 
    }
} // end class
new DownloadZipAttachments();

add_action('widgets_init', create_function('', 'return register_widget("DownloadZipAttachments");'));
