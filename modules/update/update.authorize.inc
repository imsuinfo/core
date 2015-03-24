<?php

/**
 * @file
 * Callbacks and related functions invoked by authorize.php to update projects.
 *
 * We use the Batch API to actually update each individual project on the site.
 * All of the code in this file is run at a low bootstrap level (modules are not
 * loaded), so these functions cannot assume access to the rest of the code of
 * the Update Manager module.
 */

/**
 * Updates existing projects when invoked by authorize.php.
 *
 * Callback for system_authorized_init() in
 * update_manager_update_ready_form_submit().
 *
 * @param $filetransfer
 *   The FileTransfer object created by authorize.php for use during this
 *   operation.
 * @param $projects
 *   A nested array of projects to install into the live webroot, keyed by
 *   project name. Each subarray contains the following keys:
 *   - project: The canonical project short name.
 *   - updater_name: The name of the Updater class to use for this project.
 *   - local_url: The locally installed location of new code to update with.
 */
function update_authorize_run_update($filetransfer, $projects) {
}

/**
 * Installs a new project when invoked by authorize.php.
 *
 * Callback for system_authorized_init() in
 * update_manager_install_form_submit().
 *
 * @param FileTransfer $filetransfer
 *   The FileTransfer object created by authorize.php for use during this
 *   operation.
 * @param string $project
 *   The canonical project short name (e.g., {system}.name).
 * @param string $updater_name
 *   The name of the Updater class to use for installing this project.
 * @param string $local_url
 *   The URL to the locally installed temp directory where the project has
 *   already been downloaded and extracted into.
 */
function update_authorize_run_install($filetransfer, $project, $updater_name, $local_url) {
}

/**
 * Batch callback: Copies project to its proper place when authorized to do so.
 *
 * @param string $project
 *   The canonical short name of the project being installed.
 * @param string $updater_name
 *   The name of the Updater class to use for installing this project.
 * @param string $local_url
 *   The URL to the locally installed temp directory where the project has
 *   already been downloaded and extracted into.
 * @param FileTransfer $filetransfer
 *   The FileTransfer object to use for performing this operation.
 * @param array $context
 *   Reference to an array used for Batch API storage.
 */
function update_authorize_batch_copy_project($project, $updater_name, $local_url, $filetransfer, &$context) {
}

/**
 * Batch callback: Performs actions when the authorized update batch is done.
 *
 * This processes the results and stashes them into SESSION such that
 * authorize.php will render a report. Also responsible for putting the site
 * back online and clearing the update status cache after a successful update.
 *
 * @param $success
 *   TRUE if the batch operation was successful; FALSE if there were errors.
 * @param $results
 *   An associative array of results from the batch operation.
 */
function update_authorize_update_batch_finished($success, $results) {
}

/**
 * Creates a structure of log messages.
 *
 * @param array $project_results
 *   An associative array of results from the batch operation.
 * @param string $message
 *   A string containing a log message.
 * @param bool $success
 *   (optional) TRUE if the operation the message is about was a success, FALSE
 *   if there were errors. Defaults to TRUE.
 */
function _update_batch_create_message(&$project_results, $message, $success = TRUE) {
}

/**
 * Clears cached available update status data.
 *
 * Since this function is run at such a low bootstrap level, the Update Manager
 * module is not loaded. So, we can't just call _update_cache_clear(). However,
 * the database is bootstrapped, so we can do a query ourselves to clear out
 * what we want to clear.
 *
 * Note that we do not want to just truncate the table, since that would remove
 * items related to currently pending fetch attempts.
 *
 * @see update_authorize_update_batch_finished()
 * @see _update_cache_clear()
 */
function _update_authorize_clear_update_status() {
}
