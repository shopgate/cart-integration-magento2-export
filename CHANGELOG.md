# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
## [2.9.29] - 2024-03-04
### Fixed
- export works again with MSI modules disabled

## [2.9.28] - 2023-11-08
### Fixed
- PHP 8.1 compatibility

## [2.9.27] - 2023-06-30
### Removed
- dependency to Zend_Date class

## [2.9.26] - 2023-01-18
### Added
- support bundle products in product export

## [2.9.25] - 2022-11-25
### Fixed
- error in the export of product properties

### Added
- configuration to force the export of out-of-stock products

## [2.9.24] - 2022-05-18
### Fixed
- shipping rate calculation in the import of Shopgate orders when using rates from Magento 2 during checkout

### Added
- support for Magento 2.4.4
- support for PHP 8.1.x

## [2.9.23] - 2022-01-05
### Fixed
- translations of property labels

## [2.9.22] - 2020-10-02
### Fixed
- export of products crashing when using new inventory management (`Cannot instantiate interface Magento\InventorySalesApi\Model\GetStockItemDataInterface`)

### Changed
- Now taking care to only export relations to products of types Shopgate supports and for configurable and grouped products there is a parent reference in the `uid` instead of direct product id

## [2.9.21] - 2020-07-24
### Changed
- Now exporting upsell, crosssell and simple relation individually instead of everything as upsell only

### Fixed
- Shipping and Discount tax amount in combination with auto assignment of customer groups based on vat id
- Detection of enabled multi stock inventory functionality

## [2.9.20] - 2020-03-24
### Fixed
- Missing categories in item export

## [2.9.19] - 2020-03-17
### Fixed
- Description export when it's empty
- Image export for child products

## [2.9.19] - 2020-03-17
### Fixed
- Description export when it's empty
- Image export for child products

## [2.9.18] - 2020-03-02
### Fixed
- stock class compatibility with Magento 2.3.1

## [2.9.17] - 2020-02-05
### Added
- Security enhancements
- Limit for order collection set shipping completed and refactor filter

### Fixed
- Inventory handling for Magento version >= 2.3 
- gross and net amount for shipping methods in check_cart

### Removed
- Support for PHP < 7.1
- Support for Magento < 2.2 

## [2.9.16] - 2019-11-07
### Added
- support for returning customer data cart validation
### Fixed
- sort order of products within categories
- error in product export (Enterprise Edition only)

## [2.9.15] - 2019-09-13
### Added
- filter for website specific items in product export

## [2.9.14] - 2019-08-22
### Added
- rudimentary getExternalCoupons method to cart helper to support bolt plugin which extends it

## [2.9.13] - 2019-07-18
### Changed
- EAN attribute mapping configuration will export even if it is not visible on the frontend

## [2.9.12] - 2019-06-17
### Changed
- Cron cancellation logic

## [2.9.11] - 2019-06-12
### Fixed
- coupon amount for customer group based special prices

## [2.9.10] - 2019-06-04
### Fixed
- check_cart now returns the correct item_number
- The small product images are now used as the first image of the product in the app

## [2.9.9] - 2019-08-01
### Added
- Validation off app-only cart rules in cart validation
- Shipping and cancellation synchronisation to Shopgate

## [2.9.8] - 2019-04-19
### Added
- Support of cart rule discounts
- Possibility to exclude specific items from the export
### Fixed
- Export of category paths in product export
### Changed
- Child products are now exported with type configurable

## [2.9.7]
### Changed
- Changed the GitHub composer naming so that it does not clash with Marketplace repo

## [2.9.6]
### Fixed
- Exceptions during item export
### Changed
- Composer file details

## 2.9.5
### Fixed
- Incorrect permission reference in acl.xml
- An issue with our logger printing an Array into the returned check_cart JSON

## 2.9.4
### Added
- Review export via XML
### Fixed
- Export of shipping methods for Magento 2 Enterprise Edition

## 2.9.3
### Added
- Implemented check_stock call
- Added shipping methods to check_cart export
### Fixed
- Coupons will return gross/net price based on magento settings

## 2.9.0
### Added
- get_categories call
- get_items call
- get_customer call
- check_cart call

[Unreleased]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.29...HEAD
[2.9.29]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.28...2.9.29
[2.9.28]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.27...2.9.28
[2.9.27]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.26...2.9.27
[2.9.26]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.25...2.9.26
[2.9.25]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.24...2.9.25
[2.9.24]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.23...2.9.24
[2.9.23]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.22...2.9.23
[2.9.22]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.21...2.9.22
[2.9.21]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.20...2.9.21
[2.9.20]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.19...2.9.20
[2.9.19]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.18...2.9.19
[2.9.18]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.17...2.9.18
[2.9.17]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.16...2.9.17
[2.9.16]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.15...2.9.16
[2.9.15]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.14...2.9.15
[2.9.14]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.13...2.9.14
[2.9.13]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.12...2.9.13
[2.9.12]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.11...2.9.12
[2.9.11]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.10...2.9.11
[2.9.10]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.9...2.9.10
[2.9.9]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.8...2.9.9
[2.9.8]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.7...2.9.8
[2.9.7]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.6...2.9.7
