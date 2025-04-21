Here’s the updated `README.md` with added links to WSL 2 and Ubuntu setup for Windows:

```markdown
# Local Development Setup for Pantheon with Lando

This guide covers setting up a local development environment for a WordPress site on Pantheon using Lando, WP Sync DB, and Timber. Follow the steps below to get your development environment up and running.

## Requirements

- [Lando](https://lando.dev/docs/install/) (latest version)
- [Docker](https://www.docker.com/get-started) (accompanying Docker version for Lando)
- [Node.js v22](https://nodejs.org/en/download/)
- [Pantheon Account](https://pantheon.io)
- [Timber](https://timber.github.io/)
- [Bedrock](https://roots.io/bedrock/)
- [WordPress](https://wordpress.org)
- [Node Version Manager](https://github.com/nvm-sh/nvm)

## Setup Steps

### 1. Install Lando and Docker

Follow the instructions to install **Lando** and **Docker** on your operating system:

- **Mac/Linux**: Follow the official [Lando setup instructions](https://lando.dev/docs/install/) for your platform.
- **Windows**: 
  - [Install WSL 2](https://learn.microsoft.com/en-us/windows/wsl/install)
  - [Set up Ubuntu on WSL](https://learn.microsoft.com/en-us/windows/wsl/setup/environment)
  - Then follow the Linux instructions for installing [Docker](https://docs.docker.com/engine/install/ubuntu/) and [Lando](https://docs.lando.dev/core/v3/installation.html)

### 2. Install Terminus and Connect to Pantheon

Install **Terminus** to manage your Pantheon site and connect it via a machine token:

- Install [Terminus](https://pantheon.io/docs/terminus/install)
- Log in using your Pantheon account and generate a machine token:

```bash
terminus auth:login --machine-token=<your-machine-token>
```

> 🧠 Note: You only need Terminus for connecting and initializing with Pantheon. All database-related tasks should be performed directly in Pantheon/WordPress.

### 3. Clone the Repository

Clone the repository of the WordPress site you're working on:

```bash
git clone <https://github.com/alibarkaludin/offshorly-speednik-wp>
cd offshorly-speednik-wp
```

### 4. Initialize Lando

Run the following in your project directory:

```bash
lando init
```

Follow the prompts:

```
? From where should we get your app's codebase? current working directory
? What recipe do you want to use? pantheon
? Select a Pantheon account your-email@example.com
? Which site? speednik-central-test-site
```

### 5. Set Up `.env.local`

Copy the `.env.example` file to `.env.local`:

```bash
cp .env.example .env.local
```

Edit `.env.local` and set:

```dotenv
WP_HOME='https://speednik-central-test-site.lndo.site'
WP_SITEURL="https://speednik-central-test-site.lndo.site/wp"
WP_ENV='development'
```

Replace `speednik-central-test-site` with your actual Lando app name if different.

### 6. Start Lando

Start the Lando environment:

```bash
lando start
```

### 7. Install Dependencies

Run the following to install WordPress, plugins, and other dependencies:

```bash
lando composer update
```

### 8. Install and Build the Theme

If you work on projects that use different Node.js versions, use **`nvm` (Node Version Manager)** to easily switch versions.

Then in the project root, run:

```bash
nvm use
```

> ✅ We already include an `.nvmrc` file in the repo — this will automatically use the correct Node.js version (e.g. v22).

After switching to the correct Node version, install and build the theme:

```bash
cd web/app/themes/speednik-theme
npm install
gulp build
```

> 🛠️ **Optional: Update Browsersync Proxy URL**

If you're running a different Lando site name, update the proxy URL in the `gulpfile.js` (or wherever `serve()` is defined):

```js
export function serve() {
    bs.init({
        proxy: "https://speednik-central-test-site.lndo.site", // <- change this if needed
        host: "speednik-central-test-site.lndo.site",
        open: false,
        ghostMode: false,
        serveStatic: ['.'],
        watchOptions: {
            usePolling: true
        },
        ui: false
    });

    gulp.watch(paths.styles.src, styles);
    gulp.watch(paths.scripts.src, scripts);
    gulp.watch(paths.images.src, images);
    gulp.watch(paths.twigs.src).on('change', bs.reload);
}
```

Replace both `proxy` and `host` values with your actual `.lndo.site` domain if it differs from `speednik-central-test-site.lndo.site`.

Also, install **Timber** via Composer:

```bash
lando composer update
```

> ⚠️ **Do not edit the `dist/` folder directly.**  
> It’s committed to Git because **we can’t run `gulp` on Pantheon**. All CSS/JS changes should be made in the `assets/` folder or individual `modules/` folders. After making changes, run `gulp build` locally to regenerate the `dist/` folder.

### 9. Access Your Local Site

Visit your site in the browser using the URL from `.env.local`, e.g.:

```
https://speednik-central-test-site.lndo.site
```

Complete WordPress installation with temporary values — you’ll pull the real database next.

---

### 10. Pull the Database from the Main Site

1. On the **remote (Pantheon) WordPress site**, go to **Tools > Migrate DB > Settings**.
2. Ensure **“Accept pull requests (Allow this database to be exported and downloaded)”** is checked.
3. Copy the **Connection Info**
4. On your **local WordPress site**, go to **Tools > WP Sync DB > Migrate**.
5. Select **Pull**.
6. Paste the **Remote Site URL** and **Secret Key**, and click **Connect**.
7. (Optional) Check **“Media Files”** to sync media.
8. (Optional) Check **“Save Migration Profile”** to avoid re-entering credentials later.
9. Click **Migrate DB** to pull the database and media.

---

### 11. Finalize Your Local Setup

Once the database and media are synced, check your **local site (frontend)** for any issues or errors to confirm everything is working as expected.

> 💡 It’s better to make **CMS changes** (menus, content, etc.) on the **main site (Pantheon)** first and **pull to local**. This avoids having to repeat the changes.
>
> If you do make CMS changes locally, remember to manually replicate them on the Pantheon site.

---

## Useful Links

- [Pantheon Docs](https://pantheon.io/docs)
- [Lando Docs](https://lando.dev/docs)
- [Terminus Docs](https://pantheon.io/docs/terminus)
- [Timber & Twig Docs](https://timber.github.io/docs/)
- [Twig Templating Intro](https://twig.symfony.com/doc/3.x/)
- [SASS (SCSS) Guide](https://sass-lang.com/guide)
- [Bedrock Docs](https://roots.io/bedrock/)
- [WordPress Docs](https://wordpress.org/support/)
- [Install WSL 2](https://learn.microsoft.com/en-us/windows/wsl/install)
- [Set up Ubuntu on WSL](https://learn.microsoft.com/en-us/windows/wsl/setup/environment)
- [Gulp Basics](https://gulpjs.com/docs/en/getting-started/quick-start)

```