<?php

/**
 * All custom functions should be defined in this class
 * and tied to WP hooks/filters w/in the constructor method
 */
class Custom_Functions {
  // Custom metaboxes and fields configuration
  public $metabox_namespace = 'cmb';
  public $metabox_prefix    = '_cmb_';
  // Firebase configuration
  public $firebase_url = '[...]';
  public $firebase_key = '';
  // Need to list wysiwyg metaboxes here so we can apply the
  // appropriate filters
  public $wysiwyg_fields = array(
    'title',
    'contact',
    'bio'
  );
  public function __construct() {
    // Include a helper library for adding custom metaboxes
    // 
    // https://github.com/WebDevStudios/Custom-Metaboxes-and-Fields-for-WordPress
    
    add_action('after_setup_theme', array(
      $this, 'include_custom_metabox_helper'
    ));
    // Add the "Speaker" and "Presentation" post types
    
    add_action('registered_post_type', array(
      $this, 'add_custom_post_types'
    ));
    // Define custom metaboxes to add to "edit Speaker" 
    // and "edit Presentation" pages in WP
    
    add_filter('cmb_meta_boxes', array(
      $this, 'add_metaboxes'
    ));
    // Send "Speaker" and "Presentation" metadata to Firebase
    // 
    // https://github.com/ktamas77/firebase-php
    add_action('save_post', array(
      $this, 'send_updated_metadata_to_firebase'
    ));
    // Remove unnecessary admin menus
    
    add_action('admin_menu', array(
      $this, 'remove_unneccessary_admin_menus'
    ));
  }
  // Sends speaker data (including presentations) to Firebase
  // whenever the speaker or one of his presentations is 
  // updated in WP
  public function send_updated_metadata_to_firebase($post_id) {
    if (wp_is_post_revision($post_id)) return;
    $speaker_id = $this::get_speaker_id_for_post($post_id);
   
    $data = $this::get_speaker_object($speaker_id, $this->metabox_prefix);
    $this::send_to_firebase($speaker_id, $data);
  }
  // An associative array of all of the speakers, including their
  // personal info and presentation info
  public function get_speaker_object($speaker_id) {
    $speakers_post_meta = get_post_meta($speaker_id);
    $data = $this::get_processed_metadata($speaker_id);
    $data['name']          = get_the_title($speaker_id);
    $data['presentations'] = $this::get_presentation_metadata($speaker_id);
    return $data;
  }
  public function include_custom_metabox_helper() {
    $this::include_helper('cmb/init.php');
  }
  public function add_custom_post_types() {
    $this::include_helper('post-type-helper.php');
    $args = array('supports' => array('title'));
    $speakers      = new Custom_Post_Type('speaker', $args);
    $presentations = new Custom_Post_Type('presentation', $args);
  }
  public function add_metaboxes(array $metaboxes) {
    $this::include_helper('cmb/init.php');
    // Add metaboxes to the "Speaker" custom post type
    $metaboxes['speaker_metaboxes'] = array(
      'id'         => 'speaker_metaboxes',
      'title'      => __('Speaker Info', $this->metabox_namespace),
      'pages'      => array('speaker'),
      'context'    => 'normal',
      'priority'   => 'core',
      'show_names' => true,
      'fields'     => array(
        // speaker's headshot photo
        array(
          'name' => __('Headshot Photo', $this->metabox_namespace),
          'id'   => $this->metabox_prefix . 'speaker_headshot',
          'type' => 'file'
        ),
        // speaker's job title
        array(
          'name' => __('Job Title', $this->metabox_namespace),
          'id'   => $this->metabox_prefix . 'speaker_title',
          'type' => 'wysiwyg',
          'options' => array('textarea_rows' => 2)
        ),
        // speaker's contact info
        array(
          'name' => __('Contact Info', $this->metabox_namespace),
          'id'   => $this->metabox_prefix . 'speaker_contact',
          'type' => 'wysiwyg',
          'options' => array('textarea_rows' => 5)
        ),
        // speaker's bio
        array(
          'name' => __('Bio', $this->metabox_namespace),
          'id'   => $this->metabox_prefix . 'speaker_bio',
          'type' => 'wysiwyg',
          'options' => array('textarea_rows' => 14)
        )
      )
    );
    // Now we'll add metaboxes to the "Presentation" post type
    // 
    // But first we need an array of speakers so we can assign
    // a speaker to each presentation...
    $speakers_dropdown_array = $this::get_speakers_dropdown_array();
    // Let's get on with it!
    $metaboxes['presentation_metaboxes'] = array(
      'id'         => 'presentation_metaboxes',
      'title'      => __('Presentation Info', $this->metabox_namespace),
      'pages'      => array('presentation'),
      'context'    => 'normal',
      'priority'   => 'core',
      'show_names' => true,
      'fields'     => array(
        // the speaker
        array(
          'name'    => __('Speaker', $this->metabox_namespace),
          'id'      => $this->metabox_prefix . 'presentation_speaker',
          'type'    => 'select',
          'options' => $speakers_dropdown_array
        ),
        // the presentation date
        array(
          'name'    => __('Date', $this->metabox_namespace),
          'id'      => $this->metabox_prefix . 'presentation_date',
          'type'    => 'text_date'
        ),
        // start time
        array(
          'name'    => __('Start Time', $this->metabox_namespace),
          'id'      => $this->metabox_prefix . 'presentation_start_time',
          'type'    => 'text_time'
        ),
        // end time
        array(
          'name'    => __('End Time', $this->metabox_namespace),
          'id'      => $this->metabox_prefix . 'presentation_end_time',
          'type'    => 'text_time'
        ),
        // video ID (unique part of the embed code)
        array(
          'name'    => __('Video ID', $this->metabox_namespace),
          'id'      => $this->metabox_prefix . 'presentation_video_id',
          'type'    => 'text_medium'
        ),
        // video host (wistia or livestream.com?)
        array(
          'name'    => __('Video Host', $this->metabox_namespace),
          'id'      => $this->metabox_prefix . 'presentation_video_host',
          'type'    => 'select',
          'options' => array(
            'wistia'     => __('Wistia',         $this->metabox_namespace),
            'livestream' => __('Livestream.com', $this->metabox_namespace)
          )
        ),
        // banner ad
        array(
          'name' => __('Banner', $this->metabox_namespace),
          'id'   => $this->metabox_prefix . 'presentation_banner',
          'type' => 'file'
        ),
        // promo URL for banner ad
        array(
          'name' => __('Banner URL', $this->metabox_namespace),
          'id'   => $this->metabox_prefix . 'presentation_banner_url',
          'type' => 'text_url'
        ),
        // collection of live blog entries
        array(
          'name'    => __('Live Blog Entries', $this->metabox_namespace),
          'id'      => $this->metabox_prefix . 'presentation_liveblog',
          'type'    => 'group',
          'options' => array(
            'add_button'    => __('Add Another Entry', 'cmb'),
            'remove_button' => __('Remove Entry', 'cmb'),
            'sortable'      => false
          ),
          'fields' => array(
            array(
              'name' => __('Live Blog Entry', $this->metabox_namespace),
              'id'   => $this->metabox_prefix . 'presentation_liveblog_entry',
              'type' => 'wysiwyg',
              'options' => array('textarea_rows' => 4)
            )
          )
        )
      )
    );
    return $metaboxes;
  }
  public function remove_unneccessary_admin_menus() {
    remove_menu_page('index.php');
    remove_menu_page('link-manager.php');
    remove_menu_page('edit-comments.php');
    remove_menu_page('customize.php');
    remove_menu_page('theme-editor.php');
    remove_menu_page('users.php');
    remove_menu_page('tools.php');
    remove_menu_page('plugins.php');
    remove_menu_page('edit.php');
    remove_menu_page('edit.php?post_type=page');
    remove_menu_page('upload.php');
  }
  private function get_presentation_metadata($speaker_id) {
    $presentations = array();
    $presentation_query = new WP_Query(array(
      'post_type'  => 'presentation',
      'meta_value' => $speaker_id
    ));
    if ($presentation_query->have_posts()) {
      while ($presentation_query->have_posts()) {
        $presentation_query->the_post();
        $presentation_title = get_the_title();
        $presentations[$presentation_title] = $this::get_processed_metadata(get_the_ID());
      }
    }
    return $presentations;
  }
  private function get_processed_metadata($post_id) {
    $metadata = get_post_meta($post_id);
    $metadata = $this::remove_unneccessary_metadata($metadata);
    $metadata = $this::add_video_embed_to_metadata($metadata);
    $metadata = $this::add_liveblogs_to_metadata($metadata, $post_id);
    $metadata = $this::strip_prefixes_from_metadata($metadata);
    $metadata = $this::reformat_metadata_array($metadata);
    $metadata = $this::format_wysiwyg_metadata($metadata);
    return $metadata;
  }
  private function add_liveblogs_to_metadata($metadata, $post_id) {
    if ($this::is_speaker($post_id))
      return $metadata;
    $liveblog_entries  = array();
    $liveblog_metabox  = $this->metabox_prefix . 'presentation_liveblog';
    $liveblog_postmeta = get_post_meta($post_id, $this->metabox_prefix . 'presentation_liveblog', false);
    foreach ($liveblog_postmeta as $group=>$entries) {
      foreach ($entries as $key=>$entry) {
        $liveblog_entries[] = wpautop($entry[$this->metabox_prefix . 'presentation_liveblog_entry']);
      }
    }
    $metadata[$liveblog_metabox] = $liveblog_entries;
    return $metadata;
  }
  private function remove_unneccessary_metadata($metadata) {
    foreach ($metadata as $fieldname=>$value) {
      if ($this::is_an_unnecessary_metabox_field($fieldname)) {
        unset($metadata[$fieldname]);
      }
    }
    return $metadata;
  }
  public function is_an_unnecessary_metabox_field($fieldname) {
    if (!$this::is_a_custom_metabox($fieldname)) return true;
    if (strstr($fieldname, 'headshot_id'))       return true;
    return false;
  }
  private function add_video_embed_to_metadata($metadata) {
    if ($this::has_video($metadata)) {
      $metadata['video'] = $this::get_video_embed($metadata);
      unset($metadata['video_id']);
      unset($metadata['video_host']);
    }
    return $metadata;
  }
  private function strip_prefixes_from_metadata($metadata) {
    foreach ($metadata as $old_fieldname=>$value) {
      $new_fieldname = $this::strip_prefix_from_fieldname($old_fieldname);
      $metadata[$new_fieldname] = $metadata[$old_fieldname];
      unset($metadata[$old_fieldname]);
    }
    return $metadata;
  }
  private function reformat_metadata_array($metadata) {
    foreach ($metadata as $fieldname=>$value) {
      if ($fieldname === 'liveblog') continue;
      $metadata[$fieldname] = $metadata[$fieldname][0];
    }
    return $metadata;
  }
  private function format_wysiwyg_metadata($metadata) {
    foreach ($metadata as $fieldname=>$value) {
      if (in_array($fieldname, $this->wysiwyg_fields)) {
        $metadata[$fieldname] = wpautop($value);
      }
    }
    return $metadata;
  }
  private function has_video($metadata) {
    return !(empty($metadata['video_host']) || empty('video_id'));
  }
  private function get_video_embed($metadata) {
    if ($metadata['video_host'] === 'wistia') {
      return '<iframe src="' . $metadata['video_id'] . '">';
    }
    if ($metadata['video_host'] === 'livestream') {
      return '<div id="' . $metadata['video_id'] . '">';
    }
    return null;
  }
  private function is_a_custom_metabox($fieldname) {
    return (strstr($fieldname, $this->metabox_prefix));
  }
  private function strip_prefix_from_fieldname($fieldname) {
    $fieldname = str_replace($this->metabox_prefix, '', $fieldname);
    $post_type_prefix = '/^speaker_|^presentation_/';
    
    return preg_replace($post_type_prefix, '', $fieldname);
  }
  private function get_speakers_dropdown_array() {
    $dropdown_array = array();
    $query = new WP_Query(array('post_type' => 'speaker'));
    
    if ($query->have_posts()) {
      while ($query->have_posts()) {
        $query->the_post();
        $title = get_the_title();
        $key   = get_the_ID();
        $dropdown_array[$key] = __($title, $this->metabox_namespace);
      }
    }
    return $dropdown_array;
  }
  private function send_to_firebase($key, $value) {
    $this::include_helper('fb/src/FirebaseLib.php');
    $firebase = new \Firebase\FirebaseLib($this->firebase_url, $this->firebase_key);
    $firebase->set($key, $value);
    //print_r($value);
  }
  private function get_speaker_id_for_post($post_id) {
    if ($this::is_speaker($post_id)) return $post_id;
    if ($this::is_presentation($post_id)) {
      return intval(get_post_meta($post_id, $this->metabox_prefix . 'presentation_speaker', true));
    }
    return null;
  }
  private function is_speaker($post_id) {
    return (get_post_type($post_id) === 'speaker');
  }
  private function is_presentation($post_id) {
    return (get_post_type($post_id) === 'presentation');
  }
  
  private function include_helper($helper_path) {
  	echo $helper_path;
    require_once locate_template("lib/$helper_path");
    #require_once locate_template("archive.php");
  }
}
new Custom_Functions; // run all our custom functions

php?>
