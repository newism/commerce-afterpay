# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).


## 2.2.3 - 2021-09-13
### Fixed
- Fixes #27 JS Error on redirect 

## 2.2.2 - 2021-06-24
### Fixed
- Fixes #23 - countryCode in redirect template is hardcoded
- Fixes #26 Deprecated method calls

## 2.2.1 - 2020-11-26
### Fixed
-  Fixed settings template

## 2.2.0 - 2020-11-12
### Added
-  Add “User Agent URL” as gateway option, required for some site setups [#17](https://github.com/newism/commerce-afterpay/issues/17) [@engram-design](https://github.com/engram-design)
-  Add “Merchant Reference” to define what the reference for the transaction is sent to Afterpay [#18](https://github.com/newism/commerce-afterpay/issues/18) [@engram-design](https://github.com/engram-design)
### Changed
-  Use `paymentCurrency` instead of `currency` [#14](https://github.com/newism/commerce-afterpay/issues/14) [@engram-design](https://github.com/engram-design)

## 2.1.0 - 2020-10-30
### Added
-  Added UK Clearpay Support. [#15](https://github.com/newism/commerce-afterpay/issues/15) [@engram-design](https://github.com/engram-design)

## 2.0.1 - 2020-09-29
### Fixed
-  Use rounding helper to total tax adjustment. [#10](https://github.com/newism/commerce-afterpay/issues/10) [@dwhoban](https://github.com/dwhoban)

## 2.0.0 - 2020-09-29
### Added
- CraftCommerce v3 support
### Fixed
- Orders marked as paid before completing AfterPay transaction. [#4](https://github.com/newism/commerce-afterpay/issues/4)
- User-Agent now requires Site URL. [#5](https://github.com/newism/commerce-afterpay/issues/5)
- Redirect template points to correct endpoint [#7](https://github.com/newism/commerce-afterpay/issues/7)
- Switch from fullName to concatenate first and last name in for billing and shipping addresses [#8](https://github.com/newism/commerce-afterpay/issues/8)

Shoutout to [Emily Fitton](https://punchbuggy.com.au) and [@dwhoban](https://github.com/dwhoban) for their contributions.

## 1.0.0 - 2019-02-07
### Added
- Initial release
