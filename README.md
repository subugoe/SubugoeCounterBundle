# SubugoeCounterBundle

Counter which stands for Counting Online Usage of NeTworked Electronic Resources provides the Code of Practice on the basis of which the use of electronic resources are counted and reported. This bundle aims at providing functions developed based on COUNTER Standards to fetch and process data tracked and stored in Piwik and generate reports related to usage statistics using this processed data. A particular function generates COUNTER database report 1 and platform report 1 in excel format and dispatch them via e-mail to registered customers using the document repository for which the usage data are collected. 
## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes.

### Prerequisites

* Symfony 3.0
* TwigExcelBundle

### Installing

For local development clone this repository into src/Subugoe/CounterBundle. A CounterBundle installed by composer in vendor/subugoe needs to be removed.

```mkdir src/Subugoe```

```cd Subugoe```

```git clone git@github.com:subugoe/SubugoeCounterBundle.git CounterBundle```

## License

This project is licensed under the GNU AFFERO GENERAL PUBLIC LICENSE - see the [LICENSE](LICENSE) file for details
