# Sharpspring REST API PHP tools

This PHP library contains

- a simple client class which makes REST API calls (but contains no Sharpspring
specific logic, besides the URL and authentication parameters);
- a 'Connection' object which
  - contains wrapper functions for many API methods, with extensive method
  comments about anyting confusing that was found;
  - does strict checking of API responses and tries to abstract away the
  potentially confusing parts;
  - can help deal with custom fields;
- ValueObject / Lead classes which can help deal with custom fields.

It also contains several example/partial classes implementing a synchronization
process of contact data from a source system into Sharpspring's contact/leads
database. This works with a local cache of Sharpspring leads, to minimize update
calls to the Sharpspring REST API.

## Code principles

The client class can be used standalone, though this library wasn't written for
that. If you want to take care of building your own parameters and decoding the
result yourself: go ahead. Instantiate it; call the call() method. You don't
need the rest of the library.

The aim of the Connection class is to _help you not be confused_ about
communicating with Sharpspring's REST API. It tries to help with this in the
following ways:
- It tries to stay close to Sharpspring's documentation of the REST API, which
  is your primary source of information. So all methods documented in there,
  have equal methods on the Connection object.
- It does not wrap things that do not need to be wrapped:
  - The return value of calls is equal to the 'result' array in the API response
    because that is generally the only value you will need. Unless that result
    is a one-element array by definition; then that array is unwrapped.
  - It is sometimes beneficial to use objects/classes containing the result,
    e.g. when dealing with Leads. (Especially if you are using custom fields.)
    Classes are included to do this, but you are not _forced_ to use them. If
    you like, you can keep using the result array as it is returned by the
    Sharpspring API endpoint.
- Extensive checking of the response format is done. So if a call returns a
  result, you can be sure it's vetted. (Exceptions are thrown at any sign of
  trouble.)
- Extensive comments were added around 'non-obvious' behavior of the API that
  was observed.

(The LocalLeadCache class is not discussed here.)

## Usage

```php
use SharpSpring\RestApi\Connection;
use SharpSpring\RestApi\CurlClient;

// One thing this library does not make super easy: starting. Separation of
// concerns is considered more important, so (since the actual API call was
// abstracted into CurlClient) creating a new connection takes 2 lines instead
// of 1:
$client = new CurlClient(['account_id' => ..., 'secret_key' => ...);
$api = new Connection($client);

// Get all leads updated after a certain time (notation in UTC).
$leads = $api->getLeadsDateRange('2017-01-15 10:00:00');
```

The code throws exceptions for anything strange it encounters... except for one
thing: extra properties it sees in the response, besides the array value(s)
expected by the specific API/Connection method you are calling. These are
ignored by default; it is not expected that they will ever be encountered. If
you want to have these logged, then pass a PSR-3 compatible logger object as the
second argument to the Connection constructor.

### Custom fields
In Sharpspring REST API 'objects' (arrays), custom fields are referred to by
their system name, which changes per account. To enable writing more general
code, the Connection object has a mapping from custom property to field system
name. When this mapping is set (with your own choice of property names), any
'objects' parameters in REST API calls will have their custom property names
translated automatically to the corresponding field system names.

So, Say you have leads for your shoe store, with a custom field for shoe size
which you created through the Sharpspring UI, whose system name came out as
shoe_size_384c1e3eacbb3. The following two examples are equivalent:
```php
$api->createLead([
    'firstName' => 'Roderik',
    'emailAddress' => 'rm@wyz.biz',
    'shoe_size_384c1e3eacbb3' => 12,
]);

$api->setCustomProperties('lead', ['shoeSize' => 'shoe_size_384c1e3eacbb3']);
$api->createLead([
    'firstName' => 'Roderik',
    'emailAddress' => 'rm@wyz.biz',
    'shoeSize' => 12,
]);

// Note that system names will still be OK; after setCustomProperties is called,
// you can still send in [...,'shoe_size_384c1e3eacbb3' => 12, ...]. Just don't
// set values for _both_ the field name _and_ its property alias, because then
// the library does not guarantee which of the two will be used.
```
Automatic conversion is _only_ done for 'objects' in API call parameters.
Results returned from API calls are not tampered with. If you want to have
custom field system names in API results converted back to your custom property
names, you will need to do this explicitly:
```php
$api->setCustomProperties('lead', ['shoeSize' => 'shoe_size_384c1e3eacbb3']);

$leads = $api->getLeads(['emailAddress' => 'rm@wyz.biz']);
$lead = reset($leads);
$my_lead = $api->convertSystemNames('lead', $lead);
```

### Value objects
Using arrays for API 'object' representation is just fine. But you might prefer
to use objects/classes for them. (It gives you IDE autocompletion, which also
minimizes the chance of mis-capitalized property names which the REST API does
not handle).

The base class is [ValueObject](src/ValueObject.php) and at this moment there
is a [Lead](src/Lead.php) class which implements all known fields (with comments
on where Sharpspring's API documentation is outdated).

The following example is equal to above:
```php
/**
 * If you have custom fields, you will want to define your own subclass:
 */
class ShoeStoreLead extends Lead
{
    // Define your own properties:
    public $shoeSize;
}
$api->setCustomProperties('lead', ['shoeSize' => 'shoe_size_384c1e3eacbb3']);

// This is the create call from above. Note createLead() accepts ValueObjects as
// well as arrays.
$lead = new ShoeStoreLead();
$lead->firstName = 'Roderik';
$lead->emailAddress = rm@wyz.biz';
$lead->shoeSize = 12;
$api->createLead($lead);

// And this is the 'get' call which puts the result into a new object:
$leads = $api->getLeads(['emailAddress' => 'rm@wyz.biz']);
$lead = reset($leads);
$my_lead = $api->convertSystemNames('lead', $lead);
$my_lead_obj = new ShoeStoreLead($my_lead);
```
Obviously, if you don't have any custom fields then this example gets a lot
simpler (because you don't need to subclass Lead or use setCustomProperties() /
convertSystemNames()).

In the above example, the ValueObject does not know anything about the mapping
of its properties to field system names; the Connection object handles this for
create/update operations, and after 'get' operations you need to explicitly
convert them back to custom property names before constructing the object.

There is also another way: you can set the mapping in the ValueObject instead of
the Connection.

```php
$mapping = ['shoeSize' => 'shoe_size_384c1e3eacbb3'];
// $api->setCustomProperties('lead', $mapping) is not called here.

// For create:
$lead = new ShoeStoreLead([], $mapping);
$lead->firstName = 'Roderik';
$lead->emailAddress = rm@wyz.biz';
$lead->shoeSize = 12;
$api->createLead($lead);
// Note you could also add all the properties in the first argument of the
// constructor, instead of setting them individually - although that more or
// less defeats the purpose of using a ValueObject in the first place. Setting
// 'shoeSize' works just as well as 'shoe_size_384c1e3eacbb3', in that first
// argument. Just don't set values for _both_ the field name _and_ its property
// alias, because then the library does not guarantee which of the two will be
// used.

// For 'get':
$leads = $api->getLeads(['emailAddress' => 'rm@wyz.biz']);
$lead = reset($leads);
$my_lead_obj = new ShoeStoreLead($my_lead, $mapping);
```

So: for ValueObjects that have custom fields, there is the option of setting a
mapping the connection, or setting it in the ValueObject. The latter has the
advantage that data retrieved from the REST API is automatically converted in
the constructor, but the disadvantage that the mapping needs to be set _every_
time an object is constructed.

There is another way: either hardcoding the mapping inside the object, like:

    // Override the parent's (empty) property mapping variable:
    protected $_customProperties = ['shoeSize' => 'shoe_size_384c1e3eacbb3'];

...or making your custom ValueObject subclass' constructor set it (or derive it
from somewhere). That will likely be code specific to your own situation.

Choose your own preferred approach.

## API Bugs

Most strange behavior of the Sharpspring REST API has been documented or partly
mitigated/hidden away by this library. However if you are going to do serious
work based on the API, there are a couple of things you should at least be aware
of, and decide whether you need to take these into account.

1) Values with non-standard characters (roughly: characters that would be encoded
by htmlspecialchars()) are stored in Sharpspring differently depending on
whether they are inserted through the REST API or entered through the UI. (And
for the UI, things also differ between standard and custom fields.) The '<' is
even stranger: it's _sometimes_ stored double-encoded. The gory details are in
[encoding.md](encoding.md). The only way this library has been able to mitigate
that behavior is for CurlClient to always HTML-decode any fields, whether or not
it's necessary.
Because of the HTML decoding happening transparently, you likely won't see this
behavior, but a serious application should still consider whether this is a
problem.

2) The updateLead call can change e-mail addresses of an existing lead by
submitting (at least) the existing 'id' value along with the changed e-mail
address. However if the changed e-mail happens to be used in another existing
lead already, the API will silently discard the update _but still report
success_. This is a potential issue if you are mirroring an existing contact
database where e-mail addresses are not necessarily unique, into Sharpspring.
You will need to doublecheck your updates to see whether they succeeded. (One
example of such code is in SharpspringSyncJob::finish().)

## Completeness

This code has been tested with Leads and ListMembers. More API calls are present
but not all of them have been tested extensively and some are missing. Adding
new calls is hopefully not a lot of work; pull requests are welcomed.

## Authors

* Roderik Muit - [Wyz](https://wyz.biz/)

I like contributing open source software to the world and I like opening up
semi-closed underdocumented systems. Give me a shout-out if this is useful or if
you have a contribution. Contact me if you need integration work done. (I have
experience with several other systems.)

## License

This library is licensed under the MIT License - see the
[LICENSE.md](LICENSE.md) file for details.

## Acknowledgments

* Partly sponsored by [Yellowgrape](http://www.yellowgrape.nl/), professionals
  in E-commerce strategy / marketing / design. (The synchronisation process was
  paid by them; the added work of writing code that can be open-sourced and
  carefully testing/documenting things was done in my own unpaid time.)
