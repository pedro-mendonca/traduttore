<?php

namespace Required\Traduttore;

use GP;
use GP_Translation_Set;
use WP_CLI;
use WP_CLI_Command;

/**
 * Class to handle Traduttore CLI commands.
 *
 * @since 2.0.0
 */
class CLI_Command extends WP_CLI_Command {
	/**
	 * Generate translation ZIP files for a project.
	 *
	 * ## OPTIONS
	 *
	 * <project>
	 * : Path or ID of the project to generate ZIP files for.
	 *
	 * [--force]
	 * : Force ZIP file generation, even if there were no changes since the last build.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate ZIP files for the project with ID 123.
	 *     $ wp traduttore translations build 123
	 *     ZIP file generated for translation set (ID: 1)
	 *     ZIP file generated for translation set (ID: 3)
	 *     ZIP file generated for translation set (ID: 7)
	 */
	public function build( $args, $assoc_args ) {
		if ( is_numeric( $args[0] ) ) {
			$project = GP::$project->get( $args[0] );
		} else {
			$project = GP::$project->by_path( $args[0] );
		}

		// Get the project object from the project path that was passed in.
		if ( ! $project ) {
			WP_CLI::error( 'Project not found' );
		}

		$translation_sets = (array) GP::$translation_set->by_project_id( $project->id );

		/** @var GP_Translation_Set $translation_set */
		foreach ( $translation_sets as $translation_set ) {
			$zip_provider = new ZipProvider( $translation_set );

			if ( ! $assoc_args['force'] && $translation_set->last_modified() <= ZipProvider::get_last_build_time( $translation_set ) ) {
				WP_CLI::warning( sprintf( 'No ZIP file generated for translation set as there were no changes (ID: %d)', $translation_set->id ) );

				continue;
			}

			$success = $zip_provider->generate_zip_file();

			if ( $success ) {
				WP_CLI::success( sprintf( 'ZIP file generated for translation set (ID: %d)', $translation_set->id ) );
			} else {
				WP_CLI::warning( sprintf( 'Error generating ZIP file for translation set (ID: %d)', $translation_set->id ) );
			}
		}
	}

	/**
	 * Updates project translations from GitHub repository.
	 *
	 * Finds the project the repository belongs to and updates the translations accordingly.
	 *
	 * ## OPTIONS
	 *
	 * <project|url>
	 * : Project path / ID or GitHub repository URL, e.g. https://github.com/wearerequired/required-valencia
	 *
	 * ## EXAMPLES
	 *
	 *     # Update translations from repository URL.
	 *     $ wp traduttore translations update https://github.com/wearerequired/required-valencia
	 *     Success: Updated translations for project (ID: 123)!
	 *
	 *     # Update translations from project path.
	 *     $ wp traduttore translations update required/required-valencia
	 *     Success: Updated translations for project (ID: 123)!
	 *
	 *     # Update translations from project ID.
	 *     $ wp traduttore translations update 123
	 *     Success: Updated translations for project (ID: 123)!
	 */
	public function update( $args, $assoc_args ) {
		if ( is_numeric( $args[0] ) ) {
			$project = GP::$project->get( (int) $args[0] );
		} else {
			$project = GP::$project->by_path( $args[0] );

			if ( ! $project ) {
				$project = GitHubUpdater::find_project( $args[0] );
			}
		}

		if ( ! $project ) {
			WP_CLI::error( 'Project not found' );
		}

		$github_updater = new GitHubUpdater( $project );
		$success        = $github_updater->fetch_and_update();

		if ( $success ) {
			WP_CLI::success( sprintf( 'Updated translations for project (ID: %d)!', $project->id ) );
		} else {
			WP_CLI::warning( sprintf( 'Could not update translations for project (ID: %d)!', $project->id ) );
		}
	}
}
