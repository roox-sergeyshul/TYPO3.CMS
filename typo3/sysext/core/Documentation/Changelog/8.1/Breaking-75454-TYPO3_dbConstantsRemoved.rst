
.. include:: ../../Includes.txt

=============================================
Breaking: #75454 - TYPO3_db Constants removed
=============================================

See :issue:`75454`

Description
===========

The PHP constants `TYPO3_db`, `TYPO3_db_username`, `TYPO3_db_password` and `TYPO3_db_host`
which were used when TYPO3 initialized the database connection have been removed.


Impact
======

Checking for or using the mentioned constants may lead to unexpected behavior or errors.
If not checked if the constant even was defined, the application will stop immediately.


Affected Installations
======================

Any installation which uses a third-party extension using these constants.


Migration
=========

Use the configuration data within `$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']`
to determine the username, password and host information for the default database connection.

.. index:: php, setting
