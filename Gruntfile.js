/* eslint-env node */

module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-jasmine-nodejs' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	require( 'module-alias/register' );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true,
				reportUnusedDisableDirectives: true
			},
			all: [
				'*.js',
				'**/*.js',
				'!Gruntfile.js',
				'!modules/gadget-skip.js',
				'!tests/coverage/**',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		banana: {
			options: {
				disallowDuplicateTranslations: false,
				disallowUnusedTranslations: false
			},
			all: 'i18n/'
		},
		jsonlint: {
			all: [
				'**/*.json',
				'!tests/coverage/**',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		stylelint: {
			all: [
				'**/*.css',
				'**/*.less',
				'!tests/coverage/**',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		jasmine_nodejs: {
			all: {
				options: {
					random: true
				},
				specs: [
					'tests/jasmine/**/*.spec.js'
				]
			}
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'jsonlint', 'banana', 'jasmine_nodejs', 'stylelint' ] );
	grunt.registerTask( 'default', 'test' );
};
