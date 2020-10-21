# COVID-19 TYPO3 extension

This TYPO3 extensions displays data about COVID-19 (Coronavirus SARS-CoV-2) from the German RKI.

## Example

![Example chart](Documentation/Images/example.png)

## Installation

* Get the extension
    ```
    composer require blueways/bw-covid-numbers
    ```
* Include the TypoScript template

## Usage

Add the new plugin "COVID-19 numbers" to any page and set up your desired filter options in plugin settings.

Add a new scheduler task to clear the cache and get daily new numbers.

## Developer

To customize the graph, have look at the ```initChartJs.js```. This file gets included via ```plugin.tx_bwcovidnumbers_pi1.settings.initChartJs```.

## ToDos

* Chart configuration inside plugin settings
* German translation

Feel free to contribute! [Bitbucket-Repository](https://bitbucket.org/blueways/bw_covid_numbers)
