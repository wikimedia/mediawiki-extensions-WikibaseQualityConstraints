/* jshint node: true, strict: false */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-jscs' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );

	grunt.initConfig( {
		jshint: {
			options: {
				jshintrc: true
			},
			all: '.'
		},
		jscs: {
			all: '.'
		},
		banana: {
			options: {
				disallowDuplicateTranslations: false,
				disallowUnusedTranslations: false
			},
			all: 'i18n/'
		}
	} );

	grunt.registerTask( 'test', [ 'jshint', 'jscs', 'banana' ] );
};
