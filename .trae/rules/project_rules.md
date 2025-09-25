### Overview

This is a composer package for Laravel framework. It should follows the Canvas RESTful API functions and rules.

### Pagination

Requests that return multiple items will be paginated to 10 items by default. You can set a custom per-page amount with the ?per_page parameter. There is an unspecified limit to how big you can set per_page to, so be sure to always check for the Link header.

To retrieve additional pages, the returned Link headers should be used. These links should be treated as opaque. They will be absolute urls that include all parameters necessary to retrieve the desired current, next, previous, first, or last page. The one exception is that if an access_token parameter is sent for authentication, it will not be included in the returned links, and must be re-appended.

Pagination information is provided in the Link header:

Link: <https://<canvas>/api/v1/courses/:id/discussion_topics.json?opaqueA>; rel="current",
      <https://<canvas>/api/v1/courses/:id/discussion_topics.json?opaqueB>; rel="next",
      <https://<canvas>/api/v1/courses/:id/discussion_topics.json?opaqueC>; rel="first",
      <https://<canvas>/api/v1/courses/:id/discussion_topics.json?opaqueD>; rel="last"

The possible rel values are:

- current - link to the current page of results.
- next - link to the next page of results.
- prev - link to the previous page of results.
- first - link to the first page of results.
- last - link to the last page of results.

These will only be included if they are relevant. For example, the first page of results will not contain a rel="prev" link. rel="last" may also be excluded if the total count is too expensive to compute on each request.

NOTE: Because HTTP header names are case-insensitive, please be sure you are not parsing the Link header in a case-sensitive way. The capitalization of the header name is not guaranteed.