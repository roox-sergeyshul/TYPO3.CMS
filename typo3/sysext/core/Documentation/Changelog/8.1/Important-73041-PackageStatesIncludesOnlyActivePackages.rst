
.. include:: ../../Includes.txt

===============================================================
Important: #73041 - PackageStates Includes Only Active Packages
===============================================================

See :issue:`73041`

Description
===========

The information about available packages in the system located in typo3conf/PackageStates.php was
thinned out to only include the extension keys of the active (= installed) extensions.