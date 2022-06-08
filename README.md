# Transgression
This is a custom WordPress theme that also integrates several plugins to create an event site.

The theme is built off of [Blockbase](https://blockbasetheme.com/) and customized with specific fonts and colors. See more about [FSE here](https://fullsiteediting.com/).

The main functionality is around [WooCommerce](https://woocommerce.com/) and [MailPoet](https://www.mailpoet.com/). Users can be created with the Customer role, and with that role can log in (potentially with a passwordless setup) and purchase tickets.

There is also a custom post type Applications, which is designed to hold apps for review. These have custom admin screens to help with that. Also, if MailPoet is installed, application responses can be automatically sent out with linking to customizable templates.

Applications can be created by multiple sources, but the current choice is [JetFormsBuilder](https://crocoblock.com/plugins/jetformbuilder/).

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
