# Invoice links

To create a button in Twinfield that can link to the Breww invoice you must deploy this project somewhere
on the internet.

## URL shortener

There is a rudimentary URL shortener included in this repo. This is because the field used to store the
reference to a Breww invoice only has 40 characters in Twinfield, and the variable part of the Breww
invoice URL is already longer than this.

So when running this on a webserver, the sync code will call /url?new=<url> returning a hash, which will
be sent to Twinfield. 

## Setting the FreeText3 field

In Twinfield, go to __Settings > Company Settings > Transaction__ types and then choose the transaction type
that is set in the config file (default: VRK, Sales transaction).

Under __Free text fields__ set __Free text field 3__ to:

| Usage      |  Allowed           |
| Location   |  Header            |
| Total name |  Breww short link  |
| Total type |  Text              |

and __Document imaging__ to:

| Name       |  Breww Invoice                            |
| Link       |  https://<yourdomain.com>/url/$FreeText3$ |

where the url matches where you have this code running. 
