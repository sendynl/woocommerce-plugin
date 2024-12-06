=== Sendy ===
Plugin Name: Sendy
Plugin URI: https://app.sendy.nl/
Description: A WooCommerce plugin that connects your site to the Sendy platform
Version: 3.0.3
Stable tag: 3.0.3
License: MIT
Author: Sendy
Author URI: https://sendy.nl/
Tested up to: 6.7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 8.2
WC tested up to: 9.4.2

Een plugin van Sendy voor WooCommerce waarmee je op eenvoudige wijze labels aan kunt maken voor zendingen.

== Description ==

Koppel je webshop met Sendy en je bestellingen worden automatisch omgezet in verzendlabels. Het installeren van de WooCommerce plug-in om te verbinden met Sendy doe je eenvoudig binnen 2 minuten. Je hebt geen technische kennis nodig.
Met een koppeling bespaar je niet alleen tijd, maar ook frustraties rondom het verzendproces. Al je zendingen staan nu in één overzicht, waardoor jij meer controle hebt over het verzendproces. Je importeert automatisch alle orders direct vanuit je webshop. Kies onder andere uit DHL, PostNL of DPD of combineer vervoerders. Je kunt alle orders in één keer verwerken of per order de verzendopties aanpassen.

Voordelen van de WooCommerce integratie:
- Koppel gemakkelijk zonder het uitwisselen van API-gegevens.
- Eenvoudig zendingen aanmaken en labels printen vanuit de WooCommerce webshop.
- Track en trace codes worden opgeslagen in de WooCommerce webshop.
- Met de parcelshopfinder geef je jouw klant de mogelijkheid om het pakket op te halen bij een parcelshop naar keuze.

Nog geen klant van Sendy? Ga naar www.sendy.nl en maak een gratis online account aan.

== Installation ==

1. Installeer en activeer de plug-in via de plug-in-directory
2. Ga in de back-end van je webshop naar *WooCommerce* -> *Sendy*.
3. Klik op *Verbinden*. Je gaat nu naar je Sendy verzendaccount. Mogelijk moet je hier nog inloggen.
4. Klik op *Toegang geven*.
5. Als alles gelukt is zie je bovenin WooCommerce de melding '*Authenticatie gelukt*'

= Parcelshopfinder installeren =

Laat je klanten in de checkout kiezen voor een parcelshoplevering, zodat ze de zending bij een parcelshop naar keuze kunnen ophalen.

1. Ga in de back-end van WooCommerce links in het menu naar *WooCommerce* → *Instellingen*.
2. Klik op het tabblad *Verzending*.
3. Klik bij de zone waarvoor je een parcelshopfinder wil activeren op *Bewerken*.
4. Klik op *Verzendmethode toevoegen*.
5. Kies voor de verzendmethode '*Pick-up punt levering*' en klik op *Doorgaan*.
6. Vul de instellingen voor de verzendmethode naar wens in en klik op *Aanmaken en opslaan*.

= Handleiding =

Meer informatie over de werking van de plugin is te vinden in de [kennisbank](https://support.sendy.nl/kennisbank/handleiding-woocommerce).

== External services ==

Deze plugin verbindt met de Sendy API om zendingen aan te maken.

Hierbij worden de adres- en contactgegevens van de je klanten en (optioneel) de bestelde producten doorgestuurd naar Sendy zodra je de zending aanmaakt.

Hierop zijn onze [algemene voorwaarden](https://sendy.nl/algemene-voorwaarden/) en [privacy statement](https://sendy.nl/privacy-statement/) van toepassing.

== Changelog ==

= 3.0.3 =
* Fix an issue where labels could not be downloaded
* Fix an error in the order overview tables
* Migrate data to show the shipments created by the legacy plug-in

= 3.0.2 =
* Fix an issue where labels could not be downloaded

= 3.0.1 =
* Update the tested-up-to versions for WordPress and WooCommerce

= 3.0 =
* Initial release of the new Sendy plug-in
