# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
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

[Unreleased]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.11...HEAD
[2.9.11]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.10...2.9.11
[2.9.10]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.9...2.9.10
[2.9.9]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.8...2.9.9
[2.9.8]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.7...2.9.8
[2.9.7]: https://github.com/shopgate/cart-integration-magento2-export/compare/2.9.6...2.9.7
