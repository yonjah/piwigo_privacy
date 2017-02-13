<?php
/**
* PLUGINID must be replaced by the directory name of your plugin
*/
class piwigo_privacy_maintain extends PluginMaintain
{
	function activate($plugin_version, &$errors=array()) {
		global $conf;
		if ($conf['derivative_url_style'] !== 1) {
			$errors[] =
				"Piwigo privacy requires \$conf['derivative_url_style'] to be set to 1 but it is set to {$conf['derivative_url_style']}<br/>
				Please change configuration to activate this plugin.";
		}
	}
}