<?php
/**
 *
 * @package SupportToolkit
 * @copyright (c) 2012 phpBB Group
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

/**
 * Support Toolkit tool
 *
 * An wrapper for STK tools, provides various common methods and holds the
 * actual tool object.
 */
class stk_toolbox_tool
{
	private $active;
	private $category;
	private $id;
	private $loadError;
	private $outdated;
	private $tool;
	private $vc;

	/**
	 * Initialise tool object
	 *
	 * This is a wrapper object that contains a tool for the support toolkit,
	 * this method should be called when initialising a new tool. It handles
	 * some initial validation on the tool and returns the `stk_toolbox_tool`
	 * object for the requested tool
	 *
	 * @param SplFileInfo $path Path to the tool file, the correct class name is
	 *                          determined from here
	 * @param stk_core_version_controller $vc
	 * @return string|\static The `stk_toolbox_tool` object or a string when an
	 *                        error occured.
	 */
	public function __construct(SplFileInfo $path)
	{
		$this->active		= false;
		$this->category		= substr(strrchr($path->getPath(), '/'), 1);
		$this->id			= $path->getBasename('.php');
		$this->loadError	= '';
		$this->outdated		= false;
	}

	public function validateAndLoad()
	{
		$className	= "stktool_{$this->category}_{$this->id}";
		// Test whether the class name is correctly formatted
		if (!preg_match('#^stktool_[a-zA-Z]+_[a-zA-Z_]+$#', $className))
		{
			$this->loadError = 'TOOL_CLASSNAME_WRONG_FORMAT';
			return;
		}

		$rc = new ReflectionClass($className);

		// Must implement the interface
		if (false === ($rc->implementsInterface('stk_toolbox_toolInterface')))
		{
			$this->loadError = 'TOOL_CLASS_NOT_IMPLEMENTS_INTERFACE';
			return false;
		}

		// Tool version check
		$vcr = $this->vc->testToolVersion($this->category, $this->id);
		if ($vcr == stk_core_version_controller::VERSION_BLOCKING || $vcr == stk_core_version_controller::VERSION_DISABLED)
		{
			$this->loadError = ($vcr == stk_core_version_controller::VERSION_BLOCKING) ? 'TOOL_VERSION_BLOCKED' : 'VERSION_DISABLED';
			return false;
		}
		$this->outdated	= ($vcr != stk_core_version_controller::VERSION_OK) ? true : false;
		$this->tool		= $rc->newInstanceArgs();

		return true;
	}

	public function createOverview()
	{
		global $template, $user;

		// Make sure the language file is loaded
		$this->loadToolLanguageFile();

		// Show some normal information, tool title and description
		$template->assign_vars(array(
			'L_TOOL_TITLE'			=> $user->lang(strtoupper($this->id . '_TITLE')),
			'L_TOOL_DESCRIPTION'	=> $user->lang(strtoupper($this->id . '_DESCRIPTION')),
		));

		$options = $this->tool->displayOptions();

		// Show outdated notice
		if ($this->outdated)
		{
			$template->addNotice('TOOL_OUTDATED_TITLE', 'TOOL_OUTDATED_DESCRIPTION');
		}

		// Various options
		if ($options === TOOL_OVERVIEW_TRIGGER)
		{
			$this->createStringOverview();
		}
	}

	private function runTool()
	{
		global $template, $user;

		if ($this->tool->run() === true)
		{
			$template->assign_vars(array(
				'L_TOOL_TITLE'		=> $user->lang(strtoupper($this->id . '_TITLE')),
				'L_TOOL_SUCCESS'	=> $user->lang(strtoupper($this->id . '_SUCCESS')),
			));

			stk_includes_utilities::page_header('TOOL_SUCCESS');
			stk_includes_utilities::page_footer('tool_success');
		}
		else
		{
			// Something went wrong
		}
	}

	private function createStringOverview()
	{
		$displayHandler = new stk_toolbox_display_trigger($this);

		if ($displayHandler->isConfirmed())
		{
			$this->runTool();
		}
		else
		{
			$displayHandler->setNotice(strtoupper($this->id));
			$displayHandler->display();
		}
	}

	public function loadToolLanguageFile()
	{
		global $user;
		$user->stk_add_lang("tools/{$this->category}/{$this->id}");
	}

	public function isActive()
	{
		return $this->active;
	}

	public function setActive($active = false)
	{
		$this->active = $active;
	}

	public function getID()
	{
		return $this->id;
	}

	public function getLoadError()
	{
		return $this->loadError;
	}

	public function getToolLanguageString()
	{
		return strtoupper("TOOL_{$this->category}_{$this->id}");
	}

	public function getTool()
	{
		return $this->tool;
	}

	public function getToolURL(array $params = array())
	{
		// Add cat/tool to the params
		$params['c'] = $this->category;
		$params['t'] = $this->id;

		return append_sid(STK_WEB_PATH . '/index.php', $params);
	}

	public function setVersionController(stk_core_version_controller $vc)
	{
		$this->vc = $vc;
	}
}
