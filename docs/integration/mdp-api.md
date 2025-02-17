Wicket MDP API
==============

Welcome to the Wicket API! You can use our API to access Wicket API endpoints, which can get information on various people, orders, and associated membership details from the Wicket database.

Wicket's API is implemented following the JSON API specification. Please refer to the [documentation](http://jsonapi.org/) for details about the formatting of responses.

Our API provides predictable resource oriented urls for all end points following a [REST architecture](https://en.wikipedia.org/wiki/Representational_state_transfer). We use HTTP verbs to perform [CRUD operations](https://en.wikipedia.org/wiki/Create,_read,_update_and_delete) and proper [HTTP status codes](https://en.wikipedia.org/wiki/List_of_HTTP_status_codes) in responses, including errors.

All calls to the API should be made through HTTPS to your corresponding tenant's api url of the form:

    https://<tenant>-api.wicketcloud.com


Use this documentation by first finding the resource type you are trying to query in the menu on the left and then find the appropriate operation to see detailed information about how to build your request and what to expect from the response.

API Authentication
------------------

Wicket's API authentication uses [JSON Web Tokens](https://jwt.io/). Requests are authenticated by the `bearer` authorization scheme, passing a valid JWT token in the request header:

    Authorization: Bearer <JWTtoken>


In order to generate JWT tokens you need to be provisioned with an API Secret Key and an API User [UUID](https://en.wikipedia.org/wiki/Universally_unique_identifier) with administrative privileges. To obtain these please contact our [customer support](https://support.wicket.io/).

Your API Secret Key and Admin User UUID combination grants you full unrestricted access over your Wicket account. It is very important to that you **keep your secret key safe!**. Make sure to never share it or place it in publicly accessible locations such as public code repositories, client side code etc.

Once you have these pieces of information, you can generate your JWT token to interact with Wicket's API. An introduction to how JWT tokens are created can be found [here](https://jwt.io/introduction/).

Wicket expects the following header:

    {
      "alg": "HS256", //Encryption algorithm
      "typ": "JWT".   //Type
    }


It also expects the following registered claims in the payload:

    {
      "exp": 1534414138,                             //Unix Timestamp to for token expiration
      "sub": "87882d7d-c230-4385-9231-e4fe9393f626"  //UUID of your API admin user
      "aud": "https://<tenant>-api.wicketcloud.com", //Your Wicket API URL
      "iss": "https://your-website.com"              //Optional issuer domain
    }


For better security, keep the expiration date of your JWT tokens as short as possible.

For calls to the API that involve accessing or updating data on behalf of a user, it is highly recommended to always use a token scoped for that user's person uuid. This will ensure API resources and corresponding actions are scoped to what that user has permissions to do. When using SSO through our implementation of [Central Authentication Service (CAS)](https://github.com/apereo/cas), the person ID is provided in the CAS attributes. Alternatively you can retrieve people's UUIDs from the People endpoint.

Using the API Admin user should be reserved for more special cases such as management operations or actions that shouldn't be scoped to just a specific end user.

You can find [helper libraries in various languages](https://jwt.io/#libraries-io) to help you generate JWT tokens for your specific integration with Wicket's API.

Fetch Sorting, Pagination, and Filtering
----------------------------------------

Every resource's Fetch `GET` request supports sorting, pagination, and filtering using the patterns outlined below.

### Sorting

Sorting is controlled using the sort parameter followed by the attribute name.

    ?sort=<attribute>


You may sort by multiple attributes using a comma-separated list.

    ?sort=<attribute1>,<attribute2>


By default all sorting is ascending. To sort descending, prepend a minus sign '-' to the attribute name.

    ?sort=-<attribute>


The following example will sort people by their given\_name ascending and then by their family\_name descending.

    /people?sort=given_name,-family_name


### Pagination

Pagination is controlled using the page\[number\] and page\[size\] query string parameters. Paging respects the sort query parameters.

    ?page[number]=2&page[size]=10


The following example will retrieve people from page number 2 with 10 records per page. (Records 11 - 20)

    /people?page[number]=2&page[size]=10


Pagination is enforced on almost all collection endpoints, the default page size is 25. Some endpoints may return 100-250 records by default but these typically depend on the performance characteristics of the data.

In most cases smaller page sizes <= 100 should be used as these can be load balanced more efficiently by our infrastructure. Unless otherwise noted the maximum page size is 2000.

As of March 2024, the /organizations endpoint now enforces pagination by default with a maximum page size of 500 and a default of 25 records (some existing tenants may have access to larger page sizes)

### Filtering

Filtering returns a subset of resources matching the provided criteria. It is controlled by the filter query string parameter with the attribute name and search matcher in square brackets followed by the filter's value.

    ?filter[<attribute>_<search_matcher_predicate>]=<value>


Attribute is the name of the entities attribute. Example: `updated_at`.

Search matcher predicates, like `eq` for equals, or `gteq` for greater than or equal, are documented in the table below.

    ?filter[updated_at_gteq]=2021-04-25T00:00:00+00:00


A common use for filters is to return all resources updated on or after a given DateTime. The following example returns all people updated on or after April 25, 2021.

    /people?filter[updated_at_gteq]=2021-04-25T00:00:00+00:00


You can apply multiple filters on a single fetch request.

    /people?filter[given_name_eq]=Joe&filter[family_name_eq]=Smith


For complex filtering use cases, the following endpoints support POST requests the filters provided in the request body as JSON.

    /connections/query
    /groups/query
    /group_members/query
    /organizations/query
    /organization_memberships/query
    /people/query
    /person_memberships/query


**Example**

POST /people/query?include=emails,phones,addresses

    {
      "filter": {
        "connections_organization_uuid_eq": "73cd91c1-76e4-4421-84df-acf1452cb8bc",
        "connections_tags_name_eq": "Employee",
        "membership_people_membership_uuid_in": ["8ddce1e-6938-4216-9ce9-f24a6b7cd677"],
        "membership_people_status_eq": "Active",
        "search_query": {
          "_or": [
            {
              "data_fields.section.value.field": "value"
            },
            {
              "data_fields.section2.value.field2": "value"
            }
          ]
        }
      }
    }


#### Filter Search Matcher Predicates

Predicate

Description

Notes

`*_eq`

equal

`*_not_eq`

not equal

`*_matches`

matches with `LIKE`

e.g. `q[email_matches]=%@gmail.com`

`*_does_not_match`

does not match with `LIKE`

`*_matches_any`

Matches any

`*_matches_all`

Matches all

`*_does_not_match_any`

Does not match any

`*_does_not_match_all`

Does not match all

`*_lt`

less than

`*_lteq`

less than or equal

`*_gt`

greater than

`*_gteq`

greater than or equal

`*_present`

not null and not empty

e.g. `q[name_present]=1` (SQL: `col is not null AND col != ''`)

`*_blank`

is null or empty.

(SQL: `col is null OR col = ''`)

`*_null`

is null

`*_not_null`

is not null

`*_in`

match any values in array

e.g. `q[name_in][]=Alice&q[name_in][]=Bob`

`*_not_in`

match none of values in array

`*_lt_any`

Less than any

SQL: `col < value1 OR col < value2`

`*_lteq_any`

Less than or equal to any

`*_gt_any`

Greater than any

`*_gteq_any`

Greater than or equal to any

`*_matches_any`

`*_does_not_match_any`

same as above but with `LIKE`

`*_lt_all`

Less than all

SQL: `col < value1 AND col < value2`

`*_lteq_all`

Less than or equal to all

`*_gt_all`

Greater than all

`*_gteq_all`

Greater than or equal to all

`*_matches_all`

Matches all

same as above but with `LIKE`

`*_does_not_match_all`

Does not match all

`*_not_eq_all`

none of values in a set

`*_start`

Starts with

SQL: `col LIKE 'value%'`

`*_not_start`

Does not start with

`*_start_any`

Starts with any of

`*_start_all`

Starts with all of

`*_not_start_any`

Does not start with any of

`*_not_start_all`

Does not start with all of

`*_end`

Ends with

SQL: `col LIKE '%value'`

`*_not_end`

Does not end with

`*_end_any`

Ends with any of

`*_end_all`

Ends with all of

`*_not_end_any`

`*_not_end_all`

`*_cont`

Contains value

uses `LIKE`

`*_cont_any`

Contains any of

`*_cont_all`

Contains all of

`*_not_cont`

Does not contain

`*_not_cont_any`

Does not contain any of

`*_not_cont_all`

Does not contain all of

`*_true`

is true

`*_false`

is false

Integrate Wicket with a Website
-------------------------------

We have plugins, services, and tools to make Wicket's integration with your website easy and user friendly.

### Single Sign On (SSO, OAuth, OpenID)

Wicket can be configured as an Single Sign On authentication provider with protocols like OAuth, SAML, and OpenID. Single Sign On is not required to use Wicket's API but SSO is helpful in cases when Members sign in to your website or third-party service which interacts with the Wicket API. See our [Getting Started with SSO and OAuth 2.0](https://support.wicket.io/hc/en-us/articles/4411663172631-Getting-Started-with-SSO-and-OAuth-2-0) guide to learn more.

### CMS, Wordpress and Drupal Integrations

Wicket's [Getting Started with CMS Integrations](https://support.wicket.io/hc/en-us/articles/360025699333-Getting-Started-with-CMS-Integrations) guide outlines how to integrate Wicket with your CMS to create a Member Account Center. Wicket's set of Plugins, Starter Modules, Javascript Widgets, and SDKs makes adding member self-serve features to your site easy.

Self Serve Member Features Include:

*   Protect and Access restricted pages and documents with role based security.

*   updating Person profile

*   updating communication preferences

*   onboarding, purchasing and renewing a memberships (When paired with FuseBill)
