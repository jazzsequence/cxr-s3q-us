# s3q.us

[![Deploy to Pantheon](https://github.com/jazzsequence/cxr-s3q-us/actions/workflows/deploy-to-pantheon.yml/badge.svg?branch=main)](https://github.com/jazzsequence/cxr-s3q-us/actions/workflows/deploy-to-pantheon.yml)

Main codebase for rebuilt `s3q.us` domain. Uses [Push to Pantheon GitHub Action](https://github.com/pantheon-systems/push-to-pantheon) and [WordPress (Composer Managed)](https://github.com/pantheon-systems/wordpress-composer-managed) [Bedrock](https://roots.io/bedrock)-based WordPress install.

## Setup

### 1. Clone the repository

Clone this repository to the local machine.

```bash
git clone git@github.com:jazzsequence/cxr-s3q-us.git
```

### 2. Add Pantheon remote

Add the Pantheon git repository as a remote. This is sometimes necessary to pull the generated Object Cache Pro drop-in (alternatively, you can generate locally with Lando).

```bash
site_id=$(terminus site:info cxr-s3q-us --fields=id --format=list)
git remote add pantheon ssh://codeserver.dev."$site_id"@codeserver.dev."$site_id".drush.in:2222/~/repository.git
```

### 3. Add the WordPress (Composer Managed) remote

Add the WordPress (Composer Managed) git repository as a remote. This is useful to ensure the latest updates can be pulled from the Pantheon upstream repository.

```bash
git remote add upstream git@github.com:pantheon-upstreams/wordpress-composer-managed.git
```

### 4. Install dependencies

```bash
composer install
```

## Scripts

### `composer lint`

Runs PHP syntax checking, PHPCS and shellcheck.

#### Uses

- `lint:php`
- `lint:phpcs`
- `lint:bash`

### `composer lint:phpcbf`

Runs PHP Code Beautifier and Fixer (phpcbf) on the codebase.

### `composer deploy`

Deploys the site to the Pantheon Test and Live environments.

### `composer wait`

Runs `terminus workflow:wait` to wait for the last workflow to complete.

### `composer push`

Pushes the code to Github which may trigger a deploy from GitHub to Pantheon.

#### Uses

- `wait`

### `composer update-ocp-drop-in`

Switches the Pantheon environment to SFTP for the purpose of generating a new Object Cache Pro drop-in, i.e. when a new version of OCP is released.

Once the drop-in is generated, the environment is switched back to Git mode and you will need to cherry pick the commit from Pantheon to apply it to the GitHub repository. When the drop-in is added to GitHub, it does not trigger a deploy. <!-- Should this change? -->

#### Uses

- `wait`

### `composer update-deps`

Updates composer dependencies and commits them.

### `composer update-and-deploy`

Updates composer dependencies and deploys the site to the Pantheon Test and Live environments.

#### Uses

- `update-deps`
- `push`
- `deploy`

## Powered by Bedrock and Pantheon

<table>
  <tr>
    <td><a href="https://roots.io/bedrock/">
    <img alt="Bedrock" src="https://cdn.roots.io/app/uploads/logo-bedrock.svg" height="50"></a></td>
    <td><a href="https://pantheon.io/"><img alt="Pantheon" src="docs/images/pantheon-logo-white.svg" height="50"></a></td>
  </tr>
</table>