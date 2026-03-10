# Sitegeist.GroundhogDay

> NodeTypes and PHP Tools for Events including recurrence rules 
> based on RFC 5545 - iCalendar

The package will calculate all future occurences for the next Year based on the contained nodeType mixins. 
The PHP class `Sitegeist\GroundhogDay\Domain\EventOccurrenceRepository` can be used to fetch events 
based on calendar, location and time-period.

## Authors & Sponsors

* Bernhard Schmitt - schmitt@sitegeist.de

_The development and the public-releases of this package is generously sponsored by our employer http://www.sitegeist.de._

## Installation

Sitegeist.GroundhogDay is available via packagist:
```shell
composer require sitegeist/groundhogday
```

## Usage 

### NodeTypes 

- Sitegeist.GroundhogDay:Mixin.Calendar
- Sitegeist.GroundhogDay:Mixin.Event 
- Sitegeist.GroundhogDay:Mixin.Location

# to be written

## Contributions

We will gladly accept contributions. Please send us pull requests.

In lieu of a formal styleguide, take care to maintain the existing coding style. Please make sure to contribute [PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md) compliant sources.

## License

See [LICENSE.md](./LICENSE.md)
