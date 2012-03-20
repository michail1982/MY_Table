<?php

Class MY_Table extends CI_Table
{
	/**
	 * Hidden fields
	 * @var array
	 */
	private $_hidden = array();

	/**
	 * Table cell callbacks
	 * @var array
	 */

	private $_callbacks = array();

	/**
	 * Table actions
	 * @var array
	 */
	private $_actions = array();

	/**
	 * Constructor
	 * @param array $params
	 */
	public function __construct($params = array())
	{
		parent::__construct();

		get_instance()->load->helper('url');

		foreach ($params as $key => $value)
		{
			$this->template[$key] = $value;
		}
	}

	/**
	 * Hide table cell
	 * @param string $cell_name
	 */
	public function hide($cell_name='')
	{
		$this->_hidden[] = $cell_name;
	}

	/**
	 * Set callback for table cell
	 * @param string $cell_name
	 * @param ambigous <string, array> $callback
	 */
	public function callback($cell_name,$callback)
	{
		if(is_callable($callback))
		{
			$this->_callbacks[$cell_name] = $callback;
		}
	}

	/**
	 * Add row action
	 * @param string $uri the URL
	 * @param string $title the link title
	 * @param mixed $attributes any attributes
	 */
	public function action($uri = '', $title = '', $attributes = '')
	{
		$this->_actions[] = array_combine(array('uri', 'title', 'attributes'), array($uri, $title, $attributes));
	}

	/**
	 * Parse row actions
	 * @param array $row
	 * @return string
	 */
	private function _get_actions($row)
	{
		static $search = FALSE;

		if ( ! $search )
		{
			foreach (array_keys($row) as $key)
			{
				$search[] = $this->template['action_start'] . $key . $this->template['action_end'];
			}
		}

		$_actions = array();

		foreach ($this->_actions as $action)
		{
			$_actions[] = anchor
			(
				str_replace($search, $row, $action['uri']),
				str_replace($search, $row, $action['title']),
				str_replace($search, $row, _parse_attributes($action['attributes']))
			);
		}

		return implode ( $this->template['action_delimiter'], $_actions);
	}

	/**
	 * Generate the table
	 *
	 * @access	public
	 * @param	mixed
	 * @return	string
	 */
	function generate($table_data = NULL)
	{
		// The table data can optionally be passed to this function
		// either as a database result object or an array
		if ( ! is_null($table_data))
		{
			if (is_object($table_data))
			{
				$this->_set_from_object($table_data);
			}
			elseif (is_array($table_data))
			{
				$set_heading = (count($this->heading) == 0 AND $this->auto_heading == FALSE) ? FALSE : TRUE;
				$this->_set_from_array($table_data, $set_heading);
			}
		}

		// Is there anything to display?  No?  Smite them!
		if (count($this->heading) == 0 AND count($this->rows) == 0)
		{
			return 'Undefined table data';
		}

		// Compile and validate the template date
		$this->_compile_template();


		// Build the table!

		$out = $this->template['table_open'];
		$out .= $this->newline;

		// Add any caption here
		if ($this->caption)
		{
			$out .= $this->newline;
			$out .= '<caption>' . $this->caption . '</caption>';
			$out .= $this->newline;
		}

		// Is there a table heading to display?
		if (count($this->heading) > 0)
		{
			$out .= $this->template['heading_row_start'];
			$out .= $this->newline;

			foreach($this->heading as $heading)
			{
				$out .= $this->template['heading_cell_start'];
				$out .= $heading;
				$out .= $this->template['heading_cell_end'];
			}
			// Add action heading cell
			if (sizeof($this->_actions))
			{
				$out .= $this->template['heading_cell_start'];
				$out .= $this->template['action_heading'];
				$out .= $this->template['heading_cell_end'];
			}

			$out .= $this->template['heading_row_end'];
			$out .= $this->newline;
		}

		// Build the table rows
		if (count($this->rows) > 0)
		{
			$i = 1;
			foreach($this->rows as $row)
			{
				if ( ! is_array($row))
				{
					break;
				}

				// We use modulus to alternate the row colors
				$name = (fmod($i++, 2)) ? '' : 'alt_';

				$out .= $this->template['row_'.$name.'start'];
				$out .= $this->newline;

				foreach($row as $cell_name => $cell)
				{
					//If cell is not hidden
					if ( ! in_array($cell_name, $this->_hidden))
					{
						$out .= $this->template['cell_'.$name.'start'];

						if(array_key_exists($cell_name, $this->_callbacks))
						{
							// Run cell calback
							$cell = call_user_func($this->_callbacks[$cell_name], $row);
						}
						if ($cell === "")
						{
							$out .= $this->empty_cells;
						}
						else
						{
							$out .= $cell;
						}

						$out .= $this->template['cell_'.$name.'end'];
					}
				}
				// Get Table actions
				if (sizeof($this->_actions))
				{
					$out .= $this->template['cell_'.$name.'start'];
					$out .= $this->_get_actions($row);
					$out .= $this->template['cell_'.$name.'end'];
				}

				$out .= $this->template['row_'.$name.'end'];
				$out .= $this->newline;
			}
		}

		$out .= $this->template['table_close'];

		return $out;
	}


	/**
	 * Set table data from a database result object
	 *
	 * @access	public
	 * @param	object
	 * @return	void
	 */
	function _set_from_object($query)
	{
		if ( ! is_object($query))
		{
			return FALSE;
		}

		// First generate the headings from the table column names
		if (count($this->heading) == 0)
		{
			if ( ! method_exists($query, 'list_fields'))
			{
				return FALSE;
			}

			//delete hidden headings
			$this->heading = (sizeof($this->_hidden))
				? array_diff($query->list_fields(), $this->_hidden)
				: $query->list_fields()
				;
		}

		// Next blast through the result array and build out the rows

		if ($query->num_rows() > 0)
		{
			foreach ($query->result_array() as $row)
			{
				$this->rows[] = $row;
			}
		}
	}

	/**
	 * Compile Template
	 *
	 * @access	private
	 * @return	void
	 */
	function _compile_template()
	{
		if ($this->template == NULL)
		{
			$this->template = $this->_default_template();
			return;
		}

		$temp = $this->_default_template();
		foreach (array('table_open','heading_row_start', 'heading_row_end', 'heading_cell_start', 'heading_cell_end', 'row_start', 'row_end', 'cell_start', 'cell_end', 'row_alt_start', 'row_alt_end', 'cell_alt_start', 'cell_alt_end', 'table_close', 'action_start', 'action_end', 'action_delimiter', 'action_heading') as $val)
		{
			if ( ! isset($this->template[$val]))
			{
				$this->template[$val] = $temp[$val];
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Default Template
	 *
	 * @access	private
	 * @return	void
	 */
	function _default_template()
	{
		return  array (
				'table_open' 			=> '<table border="0" cellpadding="4" cellspacing="0">',

				'heading_row_start' 	=> '<tr>',
				'heading_row_end' 		=> '</tr>',
				'heading_cell_start'	=> '<th>',
				'heading_cell_end'		=> '</th>',

				'row_start' 			=> '<tr>',
				'row_end' 				=> '</tr>',
				'cell_start'			=> '<td>',
				'cell_end'				=> '</td>',

				'row_alt_start' 		=> '<tr>',
				'row_alt_end' 			=> '</tr>',
				'cell_alt_start'		=> '<td>',
				'cell_alt_end'			=> '</td>',

				'table_close' 			=> '</table>',

				'action_start'			=> '{{',
				'action_end'			=> '}}',
				'action_delimiter'		=> '&nbsp;',
				'action_heading'		=> 'Actions'
		);
	}

}