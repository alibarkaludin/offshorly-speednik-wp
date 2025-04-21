import gulp from 'gulp';
import cleanCSS from 'gulp-clean-css';
import rename from 'gulp-rename';
import { deleteAsync } from 'del';
import sourcemaps from 'gulp-sourcemaps';
import imagemin from 'gulp-imagemin';
import uglify from 'gulp-terser';
import plumber from 'gulp-plumber';
import * as dartSass from 'sass';
import gulpSass from 'gulp-sass';
import browserSync from 'browser-sync';
import gulpIf from 'gulp-if';
import fs from 'fs-extra';

const sass = gulpSass(dartSass);
const bs = browserSync.create();

const paths = {
    styles: {
        src: ['assets/styles/**/*.scss', 'modules/**/*.scss'],
        dest: 'dist/css/'
    },
    scripts: {
        src: 'assets/scripts/**/*.js',
        dest: 'dist/scripts/'
    },
    images: {
        src: 'assets/images/**/*',
        dest: 'dist/images/'
    },
    twigs: {
        src: ['**/*.twig']
    }
};

// Clean Task
export async function clean() {
    await deleteAsync(['dist/*']);
}

// Styles Task
export function styles() {
    return gulp.src(paths.styles.src)
        .pipe(plumber())
        .pipe(sourcemaps.init())
        .pipe(sass().on('error', sass.logError))
        .pipe(cleanCSS())
        .pipe(rename({ suffix: '.min' }))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(paths.styles.dest))
        .pipe(bs.stream()); // Inject CSS changes
}

// Scripts Task
export function scripts() {
    return gulp.src(paths.scripts.src)
        .pipe(plumber())
        .pipe(sourcemaps.init())
        .pipe(uglify())
        .pipe(rename((path) => {
            if (!path.dirname.includes('pages')) {
                path.basename += '.min';
            }
        }))
        .pipe(sourcemaps.write('.'))
        .pipe(gulp.dest(paths.scripts.dest))
        .pipe(bs.stream()); // Inject JS changes
}

// Check if images directory exists
function ensureImagesFolder() {
    const imageDir = paths.images.src.replace('/**/*', '');
    if (!fs.existsSync(imageDir)) {
        fs.mkdirSync(imageDir, { recursive: true });
        return false;
    }
    return fs.readdirSync(imageDir).length > 0;
}

// Images Task
export function images() {
    return gulp.src(paths.images.src, { allowEmpty: true })
        .pipe(gulpIf(ensureImagesFolder, imagemin()))
        .pipe(gulp.dest(paths.images.dest));
}

// BrowserSync Task
export function serve() {
    bs.init({
        proxy: "https://speednik-central-test-site.lndo.site",
        host: "speednik-central-test-site.lndo.site",
        open: false,
        ghostMode: false,
        serveStatic: ['.'],
        watchOptions: {
            usePolling: true
        },
        ui: false // Disable UI to reduce conflicts
    });

    gulp.watch(paths.styles.src, styles);
    gulp.watch(paths.scripts.src, scripts);
    gulp.watch(paths.images.src, images);
    gulp.watch(paths.twigs.src).on('change', bs.reload);
}

// Build Task
export const build = gulp.series(clean, gulp.parallel(styles, scripts, images));
export const watch = gulp.series(build, serve);
export default build;