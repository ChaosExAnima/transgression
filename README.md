# Transgression
This is a custom WordPress theme that also integrates several plugins to create an event site.

The theme is built off of [Blockbase](https://blockbasetheme.com/) and customized with specific fonts and colors.

There is a custom post type Applications, which is designed to hold apps for review. These have custom admin screens to help with that.

Applications can be created by multiple sources, but the current choice is [JetFormsBuilder](https://crocoblock.com/plugins/jetformbuilder/).

There are also integrations for Auth0, Discord, Elastic Email, Forbidden Tickets, and MailPoet.

## Dev Setup
### Requirements
- Docker
- Node.js 18+
- Yarn
- [Volta](https://volta.sh/) (optional but recommended)
- PHP 8.1+
- [Composer](https://getcomposer.org/)

### Installation
1. Install dependencies with `yarn` and `composer install`.
2. Run `yarn start`. This initializes the Docker containers.
3. Once everything is up, check to see if you see WordPress on `localhost:8888`. If so, run through the installation process.
4. Run `yarn setup`. This installs the plugin and activates the theme.
5. Have fun!

To stop, just run `yarn stop`.
