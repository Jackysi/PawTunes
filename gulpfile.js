import gulp from 'gulp';
import gulpSass from 'gulp-sass';
import * as sassCompiler from 'sass';
import autoprefixer from 'gulp-autoprefixer';
import uglify from 'gulp-uglify';
import concat from 'gulp-concat';
import rename from 'gulp-rename';
import esbuild from 'gulp-esbuild';
import sourcemaps from 'gulp-sourcemaps';
import merge from 'ordered-read-streams';

const sass = gulpSass(sassCompiler);

/**
 * SCSS Files
 * For the control panel
 */
const scssFiles = [
  {
    src       : 'src/panel/scss/style.scss',
    dest      : 'panel/assets/css/',
    outputName: 'pawtunes-panel.css',
  },
];

/**
 * SCSS Files
 * Initial color schemes for different players
 */
const templateScss = [
  {
    src       : 'templates/aio-radio/scss/aio.light.scss',
    dest      : 'templates/aio-radio/css/',
    outputName: 'aio.light.css',
  },
  {
    src       : 'templates/aio-radio/scss/aio.dark.scss',
    dest      : 'templates/aio-radio/css/',
    outputName: 'aio.dark.css',
  },
  {
    src       : 'templates/html5player/scss/html5-radio-skin.scss',
    dest      : 'templates/html5player/css/',
    outputName: 'html5-radio-skin.css',
  },
  {
    src       : 'templates/simple/scss/simple.scss',
    dest      : 'templates/simple/css/',
    outputName: 'simple.css',
  },
  {
    src       : 'templates/pawtunes/scss/pawtunes.scss',
    dest      : 'templates/pawtunes/css/',
    outputName: 'pawtunes.css',
  },
  {
    src       : 'templates/pawtunes/scss/pawtunes.dark.scss',
    dest      : 'templates/pawtunes/css/',
    outputName: 'pawtunes-dark.css',
  },
];

/**
 * Control Panel Javascript
 */
const jsFiles = [
  {
    src       : [
      'src/panel/js/bootstrap.bundle.min.js',
      'src/panel/js/spectrum.js',
      'src/panel/js/sortable.js',
      'src/panel/js/custom-select.js',
      'src/panel/js/markdown.js',
      'src/panel/js/panel.js',
    ],
    dest      : 'panel/assets/js/',
    outputName: 'panel.min.js',
  },
];

/**
 * TypeScript Files for the PawTunes
 */
const tsPaths = [
  {
    src       : 'src/player/ts/pawtunes.ts',
    dest      : 'assets/js/',
    outputName: 'pawtunes.min.js',
  },
  // Templates
  {
    src       : 'src/templates/pawtunes-tpl.ts',
    dest      : 'templates/pawtunes/js/',
    outputName: 'pawtunes-tpl.min.js',
  },
  {
    src       : 'src/templates/simple.ts',
    dest      : 'templates/simple/js/',
    outputName: 'simple.min.js',
  },
];

function compileScss(files) {
  const tasks = files.map((file) => {
    return gulp.src(file.src).
        pipe(sourcemaps.init()).
        pipe(sass({
              quietDeps  : true,
              outputStyle: 'compressed',
            }, false).on('error', sass.logError),
        ).
        pipe(autoprefixer({
              overrideBrowserslist: ['last 2 versions'],
              cascade             : false,
            }),
        ).
        pipe(rename(file.outputName)).
        pipe(sourcemaps.write('.')).
        pipe(gulp.dest(file.dest)).
        on('error', function(err) {
          console.error('ERROR: ' + err.message);
        });
  });

  return merge(tasks);
}

function minifyJS() {
  const tasks = jsFiles.map((file) => {
    return gulp.src(file.src).
        pipe(sourcemaps.init()).
        pipe(concat(file.outputName)).
        pipe(
            uglify({
              output: {
                preamble: '"use strict";\n',
              },
            }),
        ).
        pipe(sourcemaps.write('.')).
        pipe(gulp.dest(file.dest));
  });

  return merge(tasks);
}

function compileTypeScript() {
  const tasks = tsPaths.map((tsFile) => {

    return gulp.src(tsFile.src) // Entry point of your application
        .pipe(esbuild({
          bundle   : true,
          outfile  : tsFile.outputName,
          platform : 'browser',
          minify   : true,
          target   : 'es2020',
          format   : 'esm',
          sourcemap: true,
          loader   : {'.ts': 'ts'},
        })).pipe(gulp.dest(tsFile.dest));
  });

  return merge(tasks);
}

function watchFiles() {
  gulp.watch(
      ['src/**/*.scss'],
      compileControlPanelSCSSFiles,
  );
  gulp.watch(
      ['templates/**/scss/*.scss'],
      compileTemplateSCSSFiles,
  );
  gulp.watch(
      [
        'src/**/*.js',
        '!src/**/*.min.js',
        'assets/js/**/*.js',
        '!assets/js/**/*.min.js',
      ],
      minifyJS,
  );
  gulp.watch('src/**/*.ts', compileTypeScript);
}

function compileControlPanelSCSSFiles() {
  return compileScss(scssFiles);
}

function compileTemplateSCSSFiles() {
  return compileScss(templateScss);
}

export const build = gulp.series(
    gulp.parallel(compileControlPanelSCSSFiles, compileTemplateSCSSFiles,
        minifyJS, compileTypeScript),
);

export const dev = gulp.series(
    build,
    watchFiles,
);