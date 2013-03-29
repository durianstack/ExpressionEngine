<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2013, EllisLab, Inc.
 * @license		http://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 2.0
 * @filesource
 */

// --------------------------------------------------------------------

/**
 * ExpressionEngine File Fieldtype Class
 *
 * @package		ExpressionEngine
 * @subpackage	Fieldtypes
 * @category	Fieldtypes
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class File_ft extends EE_Fieldtype {

	var $info = array(
		'name'		=> 'File',
		'version'	=> '1.0'
	);

	var $has_array_data = TRUE;

	var $_dirs = array();
	
	/**
	 * Constructor
	 *
	 * @access	public
	 */
	function __construct()
	{
		parent::__construct();
		ee()->load->library('file_field');
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Save the correct value {fieldir_\d}filename.ext
	 *
	 * @access	public
	 */
	function save($data)
	{
		$directory = ee()->input->post($this->field_name.'_hidden_dir');
		return ee()->file_field->format_data(urldecode($data), $directory);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Validate the upload
	 *
	 * @access	public
	 */
	function validate($data)
	{
		return ee()->file_field->validate(
			$data, 
			$this->field_name,
			$this->settings['field_required']
		);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Show the publish field
	 *
	 * @access	public
	 */
	function display_field($data)
	{
		$allowed_file_dirs		= (isset($this->settings['allowed_directories']) && $this->settings['allowed_directories'] != 'all') ? $this->settings['allowed_directories'] : '';
		$content_type			= (isset($this->settings['field_content_type'])) ? $this->settings['field_content_type'] : 'all';

		return ee()->file_field->field(
			$this->field_name,
			$data,
			$allowed_file_dirs,
			$content_type
		);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Prep the publish data
	 *
	 * @access	public
	 */
	function pre_process($data)
	{
		return ee()->file_field->parse_field($data);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Runs before the channel entries loop on the front end
	 *
	 * @param array $data	All custom field data about to be processed for the front end
	 * @return void
	 */
	function pre_loop($data)
	{
		ee()->file_field->cache_data($data);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Replace frontend tag
	 *
	 * @access	public
	 */
	function replace_tag($file_info, $params = array(), $tagdata = FALSE)
	{
		// Experimental parameter, do not use
		if (isset($params['raw_output']) && $params['raw_output'] == 'yes')
		{
			return $file_info['raw_output'];
		}
		
		// Let's allow our default thumbs to be used inside the tag pair
		if (isset($file_info['path']) && isset($file_info['filename']) && isset($file_info['extension']))
		{
			$file_info['url:thumbs'] = $file_info['path'].'_thumbs/'.$file_info['filename'].'.'.$file_info['extension'];
		}	

		// Make sure we have file_info to work with
		if ($tagdata !== FALSE AND $file_info === FALSE)
		{
			$tagdata = ee()->functions->prep_conditionals($tagdata, array());
		}
		else if ($tagdata !== FALSE)
		{
			$tagdata = ee()->functions->prep_conditionals($tagdata, $file_info);

			// -----------------------------
			// Any date variables to format?
			// -----------------------------
			$upload_date		= array();
			$modified_date		= array();

			$date_vars = array('upload_date', 'modified_date');

			foreach ($date_vars as $val)
			{
				if (preg_match_all("/".LD.$val."\s+format=[\"'](.*?)[\"']".RD."/s", ee()->TMPL->tagdata, $matches))
				{
					for ($j = 0; $j < count($matches['0']); $j++)
					{
						$matches['0'][$j] = str_replace(LD, '', $matches['0'][$j]);
						$matches['0'][$j] = str_replace(RD, '', $matches['0'][$j]);

						switch ($val)
						{
							case 'upload_date':
								$upload_date[$matches['0'][$j]] = $matches['1'][$j];
								break;
							case 'modified_date':
								$modified_date[$matches['0'][$j]] = $matches['1'][$j];
								break;
						}
					}
				}
			}

			foreach (ee()->TMPL->var_single as $key => $val)
			{
				// Format {upload_date}
				if (isset($upload_date[$key]))
				{
					$tagdata = ee()->TMPL->swap_var_single(
						$key,
						ee()->localize->format_date(
							$upload_date[$key], 
							$file_info['upload_date']
						),
						$tagdata
					);
				}

				// Format {modified_date}
				if (isset($modified_date[$key]))
				{
					$tagdata = ee()->TMPL->swap_var_single(
						$key,
						ee()->localize->format_date(
							$modified_date[$key], 
							$file_info['modified_date']
						),
						$tagdata
					);
				}
			}

			// ---------------
			// Parse the rest!
			// ---------------
			$tagdata = ee()->functions->var_swap($tagdata, $file_info);
			
			// More an example than anything else - not particularly useful in this context
			if (isset($params['backspace']))
			{
				$tagdata = substr($tagdata, 0, - $params['backspace']);
			}

			return $tagdata;
		}
		else if ( ! empty($file_info['path'])
			AND ! empty($file_info['filename'])
			AND $file_info['extension'] !== FALSE)
		{
			$full_path = $file_info['path'].$file_info['filename'].'.'.$file_info['extension'];

			if (isset($params['wrap']))
			{
				return $this->_wrap_it($file_info, $params['wrap'], $full_path);
			}

			return $full_path;
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Replace frontend tag (with a modifier catchall)
	 *
	 * Here, the modifier is the short name of the image manipulation,
	 * e.g. "small" in {about_image:small}
	 *
	 * @access	public
	 */
	function replace_tag_catchall($file_info, $params = array(), $tagdata = FALSE, $modifier)
	{
		// These are single variable tags only, so no need for replace_tag
		if ($modifier)
		{
			$key = 'url:'.$modifier;
			
			if ($modifier == 'thumbs')
			{
				if (isset($file_info['path']) && isset($file_info['filename']) && isset($file_info['extension']))
				{
			 		$data = $file_info['path'].'_thumbs/'.$file_info['filename'].'.'.$file_info['extension'];	
				}				
			}
			elseif (isset($file_info[$key]))
			{
				$data = $file_info[$key];
			}
			
			if (isset($params['wrap']))
			{
				return $this->_wrap_it($file_info, $params['wrap'], $data);
			}			
			
			return $data;
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Wrap it helper function
	 *
	 * @access	private
	 */
	function _wrap_it($file_info, $type, $full_path)
	{
		if ($type == 'link')
		{
			ee()->load->helper('url_helper');
					
			return $file_info['file_pre_format']
				.anchor($full_path, $file_info['filename'], $file_info['file_properties'])
				.$file_info['file_post_format'];
		}
		elseif ($type == 'image')
		{
			$properties = ( ! empty($file_info['image_properties'])) ? ' '.$file_info['image_properties'] : '';
					
			return $file_info['image_pre_format']
				.'<img src="'.$full_path.'"'.$properties.' alt="'.$file_info['filename'].'" />'
				.$file_info['image_post_format'];
		}
		
		return $full_path;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Display settings screen
	 *
	 * @access	public
	 */
	function display_settings($data)
	{
		$prefix = 'file';

		ee()->table->add_row(
			lang('field_content_file', $prefix.'field_content_type'),
			form_dropdown(
				'file_field_content_type',
				$this->_field_content_options(),
				$data['field_content_type'],
				'id="'.$prefix.'field_content_type"'
			)
		);
		
		$allowed_directories = ( ! isset($data['allowed_directories'])) ? 'all' : $data['allowed_directories'];

		ee()->table->add_row(
			lang('allowed_dirs_file', $prefix.'field_allowed_dirs'),
			form_dropdown(
				'file_allowed_directories',
				$this->_allowed_directories_options(),
				$allowed_directories,
				'id="'.$prefix.'field_allowed_dirs"'
			)
		);		
		
	}
	
	// --------------------------------------------------------------------
	
	public function grid_display_settings($data)
	{
		$allowed_directories = ( ! isset($data['allowed_directories'])) ? 'all' : $data['allowed_directories'];

		return array(
			$this->grid_dropdown_row(
				lang('field_content_file'),
				'field_content_type',
				$this->_field_content_options(),
				isset($data['field_content_type']) ? $data['field_content_type'] : 'all'
			),
			$this->grid_dropdown_row(
				lang('allowed_dirs_file'),
				'allowed_directories',
				$this->_allowed_directories_options(),
				$allowed_directories
			)
		);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns dropdown-ready array of allowed file types for upload
	 */
	private function _field_content_options()
	{
		return array('all' => lang('all'), 'image' => lang('type_image'));
	}

	// --------------------------------------------------------------------

	/**
	 * Returns dropdown-ready array of allowed upload directories
	 */
	private function _allowed_directories_options()
	{
		ee()->load->model('file_upload_preferences_model');

		$directory_options['all'] = lang('all');
		
		if (empty($this->_dirs))
		{
			$this->_dirs = ee()->file_upload_preferences_model->get_file_upload_preferences(1);
		}

		foreach($this->_dirs as $dir)
		{
			$directory_options[$dir['id']] = $dir['name'];
		}

		return $directory_options;
	}
	
	// --------------------------------------------------------------------

	function save_settings($data)
	{		
		return array(
			'field_content_type'	=> ee()->input->post('file_field_content_type'),
			'allowed_directories'	=> ee()->input->post('file_allowed_directories'),
			'field_fmt' 			=> 'none'
		);
	}
}

// END File_ft class

/* End of file ft.file.php */
/* Location: ./system/expressionengine/fieldtypes/ft.file.php */
