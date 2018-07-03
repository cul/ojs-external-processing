OJS ExternalProcessing Plugin
Version: 1.0
Release date: Sept. 1, 2011
Author: Center for Digital Research and Scholarship, Columbia Univ

About
-----
This plugin provides functionality for ExternalProcessing to receive/handle copyediting tasks

License
-------
This plugin is licensed under the GNU General Public License v2. See the file COPYING for the complete terms of this license.

System Requirements
-------------------
Currently this plugin will only work on UNIX systems

Installation
------------
To install the plugin:
- as Journal Manager, go into the "System Plugins" page and enable the ExternalProcessing Plugin

Configuration
------------

In order to enable the scheduled task for checking for receipt of files from ExternalProcessing, enable scheduled tasks in OJS and
add the following task to the registry/scheduledTasks.xml file:

<task class="plugins.generic.externalProcessing.CheckExternalProcessingFTP">
	<descr>Checks the ExternalProcessing FTP site for files to download and process</descr>
	<frequency minute="0"/>
</task>

Usage
-----

Known Issues
------------

Localization
------------

Contact/Support
---------------
Please email the author for support, bugfixes, or comments.
Email: <info@cdrs.columbia.edu>
