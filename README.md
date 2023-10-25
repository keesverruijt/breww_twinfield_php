# Synchronisation of sales transactions from Breww to Twinfield

At our local brewery [Het Brouwdok](https://hetbrouwdok.nl) we were investigating how to create an interface between brewery management package [BREWW](https://breww.com) and our financial book-keeping package [Twinfield](https://twinfield.com). At the time of writing, BREWW doesn't have such an interface. 

Right now this is still very much a work-in-progress, although it is functional. I am putting it up on Github as open source, not so much as something that you can use straight away, or because I endorse the use of PHP (I'd rather write Rust...) -- no, the real reason is that I want to show other developers two things:

1. How to read from BREWW's public API. There don't seem to be many examples. As you will see, BREWW's API is straightforward enough. Simple REST requests result in JSON data. 
2. How to read and write to Twinfield, in particular a real-world example on top of [php-twinfield](https://github.com/php-twinfield/twinfield). I could not find any other use of this library being published as open source, and as it has to function with Twinfield's API, it is much more convoluted. This is partly because Twinfield is using XML and SOAP, which doesn't promote direct use from any language. This is always why this repo uses PHP: this is the only library I could find written in a language that I know. There is a C# library for Twinfield, but I am not a Windows person and that library is read-only.

So, good luck with using this. It's under a liberal Apache license, so you're basically free to do with the code what you want.

## Installing

This uses PHP's 'composer' tool for third party dependencies. CD to the repo directory then run

     composer update

which will fill the `vendor/` subdirectory with the dependencies.

Next, you need to create a webserver so this code is online via the internet. See `nginx.conf.example` for an example server for this.

## Preparing Twinfield

You will need to apply as a developer and create a developer 'product'. See the (twinfield_setup/) directory.

You will also need to tell Twinfield that the API will send a free text field and that this needs to be interpreted
as a link to the Breww invoice. To do that read (INVOICE.md).

## Usage

Note that the day-to-day code lives in the repo's top directory. It can be used to run from the CLI, running `sync.php` regularly. To use this, the repo has to be live on the internet somewhere as it uses its own URL shortener (public versions either charge money or limit use). The URL shortener is used to generate URLs shorter than 40 characters (a Twinfield restriction). When you have it live you can also access `synchronize.php` from a browser.

Regards,
Kees
