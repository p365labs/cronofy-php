<?php

declare(strict_types=1);

namespace Cronofy;

use Cronofy\Exception\CronofyException;
use Cronofy\Http\CurlRequest;

class Cronofy
{
    public const USERAGENT = 'Cronofy PHP 1.0.0';
    public const API_VERSION = 'v1';

    /**
     * @var string
     */
    public $apiRootUrl;

    /**
     * @var string
     */
    public $appRootUrl;

    /**
     * @var string
     */
    public $hostDomain;

    /**
     * @var string
     */
    public $clientId;

    /**
     * @var string
     */
    public $clientSecret;

    /**
     * @var string
     */
    public $accessToken;

    /**
     * @var string
     */
    public $refreshToken;

    /**
     * @var string
     */
    public $expiresIn;

    /**
     * @var string
     */
    public $tokens;

    /**
     * @var CurlRequest
     */
    public $httpClient;

    public function __construct(array $config = [])
    {
        if (!\function_exists('curl_init')) {
            throw new CronofyException('missing cURL extension', 1);
        }

        if (!empty($config['client_id'])) {
            $this->clientId = $config['client_id'];
        }
        if (!empty($config['client_secret'])) {
            $this->clientSecret = $config['client_secret'];
        }
        if (!empty($config['access_token'])) {
            $this->accessToken = $config['access_token'];
        }
        if (!empty($config['refresh_token'])) {
            $this->refreshToken = $config['refresh_token'];
        }
        if (!empty($config['expires_in'])) {
            $this->expiresIn = $config['expires_in'];
        }

        if (!empty($config['http_client'])) {
            $this->httpClient = $config['http_client'];
        } else {
            $this->httpClient = new CurlRequest(self::USERAGENT);
        }

        $this->setUrls($config['data_center'] ?? false);
    }

    private function setUrls(bool $data_center = false): void
    {
        $data_center_addin = $data_center ? '-'.$data_center : '';

        $this->apiRootUrl = "https://api$data_center_addin.cronofy.com";
        $this->appRootUrl = "https://app$data_center_addin.cronofy.com";
        $this->hostDomain = "api$data_center_addin.cronofy.com";
    }

    private function baseHttpGet(string $path, array $auth_headers, array $params)
    {
        $url = $this->apiUrl($path);
        $url .= $this->urlParams($params);

        if (false === \filter_var($url, FILTER_VALIDATE_URL)) {
            throw new CronofyException('invalid URL');
        }

        list($result, $status_code) = $this->httpClient->httpGet($url, $auth_headers);

        return $this->handleResponse($result, $status_code);
    }

    private function apiKeyHttpGet(string $path, array $params = [])
    {
        return $this->baseHttpGet($path, $this->getApiKeyAuthHeaders(), $params);
    }

    private function httpGet(string $path, array $params = [])
    {
        return $this->baseHttpGet($path, $this->getAuthHeaders(), $params);
    }

    private function baseHttpPost(string $path, array $auth_headers, array $params = [])
    {
        $url = $this->apiUrl($path);

        if (false === \filter_var($url, FILTER_VALIDATE_URL)) {
            throw new CronofyException('invalid URL');
        }

        list($result, $status_code) = $this->httpClient->httpPost($url, $params, $auth_headers);

        return $this->handleResponse($result, $status_code);
    }

    private function httpPost(string $path, array $params = [])
    {
        return $this->baseHttpPost($path, $this->getAuthHeaders(true), $params);
    }

    private function apiKeyHttpPost(string $path, array $params = [])
    {
        return $this->baseHttpPost($path, $this->getApiKeyAuthHeaders(true), $params);
    }

    private function baseHttpDelete(string $path, $auth_headers, array $params = []): string
    {
        $url = $this->apiUrl($path);

        if (false === \filter_var($url, FILTER_VALIDATE_URL)) {
            throw new CronofyException('invalid URL');
        }

        list($result, $status_code) = $this->httpClient->httpDelete($url, $params, $auth_headers);

        return $this->handleResponse($result, $status_code);
    }

    private function httpDelete(string $path, array $params = []): string
    {
        return $this->baseHttpDelete($path, $this->getAuthHeaders(true), $params);
    }

    public function getAuthorizationURL(array $params): string
    {
        /*
          Array $params : An array of additional paramaters
          redirect_uri : String The HTTP or HTTPS URI you wish the user's authorization request decision to be redirected to. REQUIRED
          scope : An array of scopes to be granted by the access token. Possible scopes detailed in the Cronofy API documentation. REQUIRED
          delegated_scope : Array. An array of scopes to be granted that will be allowed to be granted to the account's users. OPTIONAL
          state : String A value that will be returned to you unaltered along with the user's authorization request decision. OPTIONAL
          avoid_linking : Boolean when true means we will avoid linking calendar accounts together under one set of credentials. OPTIONAL
          link_token : String The link token to explicitly link to a pre-existing account. OPTIONAL

          Response :
          String $url : The URL to authorize your access to the Cronofy API
         */

        $scope_list = \rawurlencode(\join(' ', $params['scope']));

        $url = $this->appRootUrl.'/oauth/authorize?response_type=code&client_id='
            .$this->clientId.'&redirect_uri='.\urlencode($params['redirect_uri']).'&scope='.$scope_list;

        if (!empty($params['state'])) {
            $url .= '&state='.$params['state'];
        }
        if (!empty($params['avoid_linking'])) {
            $url .= '&avoid_linking='.$params['avoid_linking'];
        }
        if (!empty($params['link_token'])) {
            $url .= '&link_token='.$params['link_token'];
        }
        if (!empty($params['delegated_scope'])) {
            $url .= '&delegated_scope='.\rawurlencode(\join(' ', $params['delegated_scope']));
        }

        return $url;
    }

    public function getEnterpriseConnectAuthorizationUrl(array $params): string
    {
        /*
          Array $params : An array of additional parameters
          redirect_uri : String. The HTTP or HTTPS URI you wish the user's authorization request decision to be redirected to. REQUIRED
          scope : Array. An array of scopes to be granted by the access token. Possible scopes detailed in the Cronofy API documentation. REQUIRED
          delegated_scope : Array. An array of scopes to be granted that will be allowed to be granted to the account's users. REQUIRED
          state : String. A value that will be returned to you unaltered along with the user's authorization request decsion. OPTIONAL

          Response :
          $url : String. The URL to authorize your enterprise connect access to the Cronofy API
         */

        $scope_list = \rawurlencode(\join(' ', $params['scope']));
        $delegated_scope_list = \rawurlencode(\join(' ', $params['delegated_scope']));

        $url = $this->appRootUrl.'/enterprise_connect/oauth/authorize?response_type=code&client_id='
            .$this->clientId.'&redirect_uri='.\urlencode($params['redirect_uri']).'&scope='
            .$scope_list.'&delegated_scope='.$delegated_scope_list;

        if (!empty($params['state'])) {
            $url .= '&state='.\rawurlencode($params['state']);
        }

        return $url;
    }

    public function requestToken(array $params)
    {
        /*
          Array $params : An array of additional paramaters
          redirect_uri : String The HTTP or HTTPS URI you wish the user's authorization request decision to be redirected to. REQUIRED
          code: The short-lived, single-use code issued to you when the user authorized your access to their account as part of an Authorization  REQUIRED

          Response :
          true if successful, error string if not
         */
        $postfields = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $params['code'],
            'redirect_uri' => $params['redirect_uri'],
        ];

        $tokens = $this->httpPost('/oauth/token', $postfields);

        if (!empty($tokens['access_token'])) {
            $this->accessToken = $tokens['access_token'];
            $this->refreshToken = $tokens['refresh_token'];
            $this->expiresIn = $tokens['expires_in'];
            $this->tokens = $tokens;

            return true;
        }

        return $tokens['error'];
    }

    public function requestDelegatedAuthorization(array $params): string
    {
        /*
          Array $params : An array of additional parameters
          profile_id : String. This specifies the ID of the profile you wish to get delegated authorization through.
          email : String. The email address of the account or resource to receive delegated access to.
          callback_url: String. The URL to callback with the result of the delegated access request.
          scope : array. The scope of the privileges you want the eventual access_token to grant.
          state : String. A value that will be returned to you unaltered along with the delegated authorization request decision.
         */
        if (isset($params['scope']) && 'array' == \gettype($params['scope'])) {
            $params['scope'] = \join(' ', $params['scope']);
        }

        return $this->httpPost('/'.self::API_VERSION.'/delegated_authorizations', $params);
    }

    public function requestLinkToken(): string
    {
        /*
          returns $result - The link_token to explicitly link to a pre-existing account. Details are available in the Cronofy API Documentation
         */
        return $this->httpPost('/'.self::API_VERSION.'/link_tokens');
    }

    public function refreshToken()
    {
        /*
          String $refresh_token : The refresh_token issued to you when the user authorized your access to their account. REQUIRED

          Response :
          true if successful, error string if not
         */
        $postfields = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
        ];

        $tokens = $this->httpPost('/oauth/token', $postfields);

        if (!empty($tokens['access_token'])) {
            $this->accessToken = $tokens['access_token'];
            $this->refreshToken = $tokens['refresh_token'];
            $this->expiresIn = $tokens['expires_in'];
            $this->tokens = $tokens;

            return true;
        }

        return $tokens['error'];
    }

    public function revokeAuthorization(string $token): string
    {
        /*
          String token : Either the refresh_token or access_token for the authorization you wish to revoke. REQUIRED

          Response :
          true if successful, error string if not
         */
        $postfields = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'token' => $token,
        ];

        return $this->httpPost('/oauth/token/revoke', $postfields);
    }

    public function revokeProfile(string $profile_id): string
    {
        /*
          String profile_id : The profile_id of the profile you wish to revoke access to. REQUIRED
         */
        return $this->httpPost('/'.self::API_VERSION.'/profiles/'.$profile_id.'/revoke');
    }

    public function applicationCalendar(string $application_calendar_id)
    {
        /*
          application_calendar_id : String The identifier for the application calendar to create

          Response :
          true if successful, error string if not
         */
        $postfields = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'application_calendar_id' => $application_calendar_id,
        ];

        $application_calendar = $this->httpPost('/v1/application_calendars', $postfields);

        if (!empty($application_calendar['access_token'])) {
            $this->accessToken = $application_calendar['access_token'];
            $this->refreshToken = $application_calendar['refresh_token'];
            $this->expiresIn = $application_calendar['expires_in'];
            $this->tokens = $application_calendar;

            return $application_calendar;
        }

        return $application_calendar['error'];
    }

    public function getAccount(): string
    {
        /*
          returns $result - info for the user logged in. Details are available in the Cronofy API Documentation
         */
        return $this->httpGet('/'.self::API_VERSION.'/account');
    }

    public function getUserInfo(): string
    {
        /*
          returns $result - userinfo for the user logged in. Details are available in the Cronofy API Documentation
         */
        return $this->httpGet('/'.self::API_VERSION.'/userinfo');
    }

    public function getProfiles(): string
    {
        /*
          returns $result - list of all the authenticated user's calendar profiles. Details are available in the Cronofy API Documentation
         */
        return $this->httpGet('/'.self::API_VERSION.'/profiles');
    }

    public function listCalendars(): string
    {
        /*
          returns $result - Array of calendars. Details are available in the Cronofy API Documentation
         */
        return $this->httpGet('/'.self::API_VERSION.'/calendars');
    }

    public function listAccessibleCalendars(string $profileId): array
    {
        return $this->httpGet('/'.self::API_VERSION.'/accessible_calendars', ['profile_id' => $profileId]);
    }

    public function readEvents(array $params): object
    {
        /*
          Date from : The minimum date from which to return events. Defaults to 16 days in the past. OPTIONAL
          Date to : The date to return events up until. Defaults to 201 days in the future. OPTIONAL
          String tzid : A string representing a known time zone identifier from the IANA Time Zone Database. REQUIRED
          Boolean include_deleted : Indicates whether to include or exclude events that have been deleted.
          Defaults to excluding deleted events. OPTIONAL
          Boolean include_moved: Indicates whether events that have ever existed within the given window should be
          included or excluded from the results. Defaults to only include events currently within the search window. OPTIONAL
          Time last_modified : The Time that events must be modified on or after in order to be returned.
          Defaults to including all events regardless of when they were last modified. OPTIONAL
          Boolean include_managed : Indiciates whether events that you are managing for the account should be included
          or excluded from the results. Defaults to include only non-managed events. OPTIONAL
          Boolean only_managed : Indicates whether only events that you are managing for the account should be included
          in the results. OPTIONAL
          Array calendar_ids : Restricts the returned events to those within the set of specified calendar_ids.
          Defaults to returning events from all of a user's calendars. OPTIONAL
          Boolean localized_times : Indicates whether the events should have their start and end times returned with any
          available localization information. Defaults to returning start and end times as simple Time values. OPTIONAL
          Boolean include_geo : Indicates whether the events should have their location's latitude and longitude
          returned where available. OPTIONAL

          returns $result - Array of events
         */
        $url = $this->apiUrl('/'.self::API_VERSION.'/events');

        return new PagedResultIterator($this, 'events', $this->getAuthHeaders(), $url, $this->urlParams($params));
    }

    public function freeBusy(array $params): PagedResultIterator
    {
        /*
          Date from : The minimum date from which to return free-busy information. Defaults to 16 days in the past. OPTIONAL
          Date to : The date to return free-busy information up until. Defaults to 201 days in the future. OPTIONAL
          String tzid : A string representing a known time zone identifier from the IANA Time Zone Database. REQUIRED
          Boolean include_managed : Indiciates whether events that you are managing for the account should be included or
          excluded from the results. Defaults to include only non-managed events. OPTIONAL
          Array calendar_ids : Restricts the returned free-busy information to those within the set of specified calendar_ids.
          Defaults to returning free-busy information from all of a user's calendars. OPTIONAL
          Boolean localized_times : Indicates whether the free-busy information should have their start and end times returned
          with any available localization information. Defaults to returning start and end times as simple Time values. OPTIONAL

          returns $result - Array of events
         */
        $url = $this->apiUrl('/'.self::API_VERSION.'/free_busy');

        return new PagedResultIterator($this, 'free_busy', $this->getAuthHeaders(), $url, $this->urlParams($params));
    }

    public function upsertEvent(array $params): string
    {
        /*
          calendar_id : The calendar_id of the calendar you wish the event to be added to. REQUIRED
          String event_id : The String that uniquely identifies the event. REQUIRED
          String summary : The String to use as the summary, sometimes referred to as the name, of the event. REQUIRED
          String description : The String to use as the description, sometimes referred to as the notes, of the event. OPTIONAL
          String tzid : A String representing a known time zone identifier from the IANA Time Zone Database. OPTIONAL
          Time start: The start time can be provided as a simple Time string or an object with two attributes, time and tzid. REQUIRED
          Time end: The end time can be provided as a simple Time string or an object with two attributes, time and tzid. REQUIRED
          String location.description : The String describing the event's location. OPTIONAL
          String location.lat : The String describing the event's latitude. OPTIONAL
          String location.long : The String describing the event's longitude. OPTIONAL
          Array reminders : An array of arrays detailing a length of time and a quantity. OPTIONAL
                            for example: array(array("minutes" => 30), array("minutes" => 1440))
          Boolean reminders_create_only: A Boolean specifying whether reminders should only be applied when creating an event. OPTIONAL
          String transparency : The transparency of the event. Accepted values are "transparent" and "opaque". OPTIONAL
          Array attendees : An array of "invite" and "reject" arrays which are lists of attendees to invite and remove from the event. OPTIONAL
                            for example: array("invite" => array(array("email" => "new_invitee@test.com", "display_name" => "New Invitee"))
                                               "reject" => array(array("email" => "old_invitee@test.com", "display_name" => "Old Invitee")))

          returns true on success, associative array of errors on failure
         */
        $postfields = [
            'event_id' => $params['event_id'],
            'summary' => $params['summary'],
            'start' => $params['start'],
            'end' => $params['end'],
        ];

        return $this->baseUpsertEvent($postfields, $params);
    }

    public function upsertExternalEvent(array $params): string
    {
        /*
          calendar_id : The calendar_id of the calendar you wish the event to be added to. REQUIRED
          String event_uid : The String that uniquely identifies the event. REQUIRED
          String summary : The String to use as the summary, sometimes referred to as the name, of the event. REQUIRED
          String description : The String to use as the description, sometimes referred to as the notes, of the event. OPTIONAL
          String tzid : A String representing a known time zone identifier from the IANA Time Zone Database. OPTIONAL
          Time start: The start time can be provided as a simple Time string or an object with two attributes, time and tzid. REQUIRED
          Time end: The end time can be provided as a simple Time string or an object with two attributes, time and tzid. REQUIRED
          String location.description : The String describing the event's location. OPTIONAL
          String location.lat : The String describing the event's latitude. OPTIONAL
          String location.long : The String describing the event's longitude. OPTIONAL
          Array reminders : An array of arrays detailing a length of time and a quantity. OPTIONAL
                            for example: array(array("minutes" => 30), array("minutes" => 1440))
          Boolean reminders_create_only: A Boolean specifying whether reminders should only be applied when creating an event. OPTIONAL
          String transparency : The transparency of the event. Accepted values are "transparent" and "opaque". OPTIONAL
          Array attendees : An array of "invite" and "reject" arrays which are lists of attendees to invite and remove from the event. OPTIONAL
                            for example: array("invite" => array(array("email" => "new_invitee@test.com", "display_name" => "New Invitee"))
                                               "reject" => array(array("email" => "old_invitee@test.com", "display_name" => "Old Invitee")))

          returns true on success, associative array of errors on failure
         */
        $postFields = [
            'event_uid' => $params['event_uid'],
            'summary' => $params['summary'],
            'start' => $params['start'],
            'end' => $params['end'],
        ];

        return $this->baseUpsertEvent($postFields, $params);
    }

    private function baseUpsertEvent(array $postFields, array $params): string
    {
        if (!empty($params['description'])) {
            $postFields['description'] = $params['description'];
        }
        if (!empty($params['tzid'])) {
            $postFields['tzid'] = $params['tzid'];
        }
        if (!empty($params['location'])) {
            $postFields['location'] = $params['location'];
        }
        if (!empty($params['reminders'])) {
            $postFields['reminders'] = $params['reminders'];
        }
        if (!empty($params['reminders_create_only'])) {
            $postFields['reminders_create_only'] = $params['reminders_create_only'];
        }
        if (!empty($params['event_private'])) {
            $postFields['event_private'] = $params['event_private'];
        }
        if (!empty($params['transparency'])) {
            $postFields['transparency'] = $params['transparency'];
        }
        if (!empty($params['attendees'])) {
            $postFields['attendees'] = $params['attendees'];
        }

        return $this->httpPost('/'.self::API_VERSION.'/calendars/'.$params['calendar_id'].'/events', $postFields);
    }

    public function deleteEvent(array $params): string
    {
        /*
          calendar_id : The calendar_id of the calendar you wish the event to be removed from. REQUIRED
          String event_id : The String that uniquely identifies the event. REQUIRED

          returns true on success, associative array of errors on failure
         */
        $postFields = ['event_id' => $params['event_id']];

        return $this->httpDelete('/'.self::API_VERSION.'/calendars/'.$params['calendar_id'].'/events', $postFields);
    }

    public function deleteExternalEvent(array $params): string
    {
        /*
          calendar_id : The calendar_id of the calendar you wish the event to be removed from. REQUIRED
          String event_uid : The String that uniquely identifies the event. REQUIRED

          returns true on success, associative array of errors on failure
         */
        $postFields = ['event_uid' => $params['event_uid']];

        return $this->httpDelete('/'.self::API_VERSION.'/calendars/'.$params['calendar_id'].'/events', $postFields);
    }

    public function createChannel(array $params): array
    {
        /*
          String callback_url : The URL that is notified whenever a change is made. REQUIRED

          returns $result - Details of new channel. Details are available in the Cronofy API Documentation
        */
        $postFields = ['callback_url' => $params['callback_url']];

        if (!empty($params['filters'])) {
            $postFields['filters'] = $params['filters'];
        }

        return $this->httpPost('/'.self::API_VERSION.'/channels', $postFields);
    }

    public function listChannels(): string
    {
        /*
          returns $result - Array of channels. Details are available in the Cronofy API Documentation
         */
        return $this->httpGet('/'.self::API_VERSION.'/channels');
    }

    public function closeChannel(array $params): string
    {
        /*
          channel_id : The ID of the channel to be closed. REQUIRED

          returns $result - Array of channels. Details are available in the Cronofy API Documentation
         */
        return $this->httpDelete('/'.self::API_VERSION.'/channels/'.$params['channel_id']);
    }

    public function authorizeWithServiceAccount(array $params): array
    {
        /*
          email : The email of the user to be authorized. REQUIRED
          scope : The scopes to authorize for the user. REQUIRED
          callback_url : The URL to return to after authorization. REQUIRED
         */
        if (isset($params['scope']) && 'array' == \gettype($params['scope'])) {
            $params['scope'] = \join(' ', $params['scope']);
        }

        return $this->httpPost('/'.self::API_VERSION.'/service_account_authorizations', $params);
    }

    public function elevatedPermissions(array $params): array
    {
        /*
          permissions : The permissions to elevate to. Should be in an array of `array($calendar_id, $permission_level)`. REQUIRED
          redirect_uri : The application's redirect URI. REQUIRED
         */
        return $this->httpPost('/'.self::API_VERSION.'/permissions', $params);
    }

    public function createCalendar(array $params): array
    {
        /*
          profile_id : The ID for the profile on which to create the calendar. REQUIRED
          name : The name for the created calendar. REQUIRED
         */
        return $this->httpPost('/'.self::API_VERSION.'/calendars', $params);
    }

    public function resources(): string
    {
        /*
          returns $result - Array of resources. Details
          are available in the Cronofy API Documentation
         */
        return $this->httpGet('/'.self::API_VERSION.'/resources');
    }

    public function changeParticipationStatus(array $params): array
    {
        /*
          calendar_id : The ID of the calendar holding the event. REQUIRED
          event_uid : The UID of the event to chang ethe participation status of. REQUIRED
          status : The new participation status for the event. Accepted values are: accepted, tentative, declined. REQUIRED
         */
        $postFields = [
            'status' => $params['status'],
        ];

        return $this->httpPost('/'.self::API_VERSION.'/calendars/'.$params['calendar_id'].'/events/'.$params['event_uid'].'/participation_status', $postFields);
    }

    public function availability(array $params): array
    {
        /*
          participants : An array of the groups of participants whose availability should be taken into account. REQUIRED
                         for example: array(
                                        array("members" => array(
                                          array("sub" => "acc_567236000909002"),
                                          array("sub" => "acc_678347111010113")
                                        ), "required" => "all")
                                      )
          required_duration : Duration that an available period must last to be considered viable. REQUIRED
                         for example: array("minutes" => 60)

          start_interval : Duration that an events can start on for example: array("minutes" => 60)
          buffer : Buffer to apply before or after events can start
                          for example:
                              array(
                                  array("before" => array("minutes" => 30)),
                                  array("after" => array("minutes" => 30))
                              )
          available_periods : An array of available periods within which suitable matches may be found. REQUIRED
                         for example: array(
                                        array("start" => "2017-01-01T09:00:00Z", "end" => "2017-01-01T18:00:00Z"),
                                        array("start" => "2017-01-02T09:00:00Z", "end" => "2017-01-02T18:00:00Z")
                                      )
         */
        $postFields = [
            'available_periods' => $params['available_periods'],
            'participants' => $params['participants'],
            'required_duration' => $params['required_duration'],
        ];

        if (!empty($params['buffer'])) {
            $postFields['buffer'] = $params['buffer'];
        }
        if (!empty($params['max_results'])) {
            $postFields['max_results'] = $params['max_results'];
        }
        if (!empty($params['start_interval'])) {
            $postFields['start_interval'] = $params['start_interval'];
        }
        if (!empty($params['response_format'])) {
            $postFields['response_format'] = $params['response_format'];
        }

        return $this->apiKeyHttpPost('/'.self::API_VERSION.'/availability', $postFields);
    }

    public function realTimeScheduling(array $params): string
    {
        /*
          oauth: An object of redirect_uri and scope following the event creation
                 for example: array(
                                "redirect_uri" => "http://test.com/",
                                "scope" => "test_scope"
                              )
          event: An object with an event's details
                 for example: array(
                                "event_id" => "test_event_id",
                                "summary" => "Add to Calendar test event",
                              )
          availability: An object holding the event's availability information
                 for example: array(
                                "participants" => array(
                                  array(
                                    "members" => array(
                                      array(
                                        "sub" => "acc_567236000909002"
                                        "calendar_ids" => array("cal_n23kjnwrw2_jsdfjksn234")
                                      )
                                    ),
                                    "required" => "all"
                                  )
                                ),
                                "required_duration" => array(
                                  "minutes" => 60
                                ),
                                "available_periods" => array(
                                  array(
                                    "start" => "2017-01-01T09:00:00Z",
                                    "end" => "2017-01-01T17:00:00Z"
                                  )
                                )
                              )
          target_calendars: An object holding the calendars for the event to be inserted into
                  for example: array(
                    array(
                      "sub" => "acc_567236000909002",
                      "calendar_id" => "cal_n23kjnwrw2_jsdfjksn234"
                    )
                  )
          tzid: the timezone to create the event in
                for example:  'Europe/London'
         */

        $postFields = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'oauth' => $params['oauth'],
            'event' => $params['event'],
            'availability' => $params['availability'],
            'target_calendars' => $params['target_calendars'],
            'tzid' => $params['tzid'],
        ];

        return $this->httpPost('/'.self::API_VERSION.'/real_time_scheduling', $postFields);
    }

    public function realTimeSequencing(array $params): string
    {
        /*
          oauth: An object of redirect_uri and scope following the event creation
                 for example: array(
                                "redirect_uri" => "http://test.com/",
                                "scope" => "test_scope"
                              )
          event: An object with an event's details
                 for example: array(
                                "event_id" => "test_event_id",
                                "summary" => "Add to Calendar test event",
                              )
          availability: An object holding the event's availability information
                for example: array(
                        "sequence" => array(
                            array(
                                "sequence_id" => "123",
                                "ordinal" => 1,
                                "participants" => array(
                                    array(
                                        "members" => array(
                                            array(
                                                "sub" => "acc_567236000909002",
                                                "calendar_ids" => array("cal_n23kjnwrw2_jsdfjksn234")
                                            )
                                        ),
                                        "required" => "all"
                                    )
                                ),
                                "event" => $event,
                                "required_duration" => array(
                                    "minutes" => 60
                                ),
                            ),
                        ),
                        "available_periods" => array(
                            array(
                                "start" => "2017-01-01T09:00:00Z",
                                "end" => "2017-01-01T17:00:00Z"
                            )
                        )
                    );
          target_calendars: An object holding the calendars for the event to be inserted into
                  for example: array(
                    array(
                      "sub" => "acc_567236000909002",
                      "calendar_id" => "cal_n23kjnwrw2_jsdfjksn234"
                    )
                  )
          tzid: the timezone to create the event in
                for example:  'Europe/London'
         */

        $postFields = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'oauth' => $params['oauth'],
            'event' => $params['event'],
            'availability' => $params['availability'],
            'target_calendars' => $params['target_calendars'],
            'tzid' => $params['tzid'],
        ];

        return $this->httpPost('/'.self::API_VERSION.'/real_time_sequencing', $postFields);
    }

    public function addToCalendar(array $params): array
    {
        /*
          oauth: An object of redirect_uri and scope following the event creation
                 for example: array(
                                "redirect_uri" => "http://test.com/",
                                "scope" => "test_scope"
                              )
          event: An object with an event's details
                 for example: array(
                                "event_id" => "test_event_id",
                                "summary" => "Add to Calendar test event",
                                "start" => "2017-01-01T12:00:00Z",
                                "end" => "2017-01-01T15:00:00Z"
                              )
         */

        $postFields = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'oauth' => $params['oauth'],
            'event' => $params['event'],
        ];

        return $this->httpPost('/'.self::API_VERSION.'/add_to_calendar', $postFields);
    }

    public function createSmartInvite(array $params): string
    {
        /*
          Array event: An object with an event's details REQUIRED
                 for example: array(
                                "summary" => "Add to Calendar test event",
                                "start" => "2017-01-01T12:00:00Z",
                                "end" => "2017-01-01T15:00:00Z"
                              )
          Array recipient: An object with recipient details REQUIRED
                     for example: array(
                         "email" => "example@example.com"
                     )
          String smart_invite_id: A string representing the id for the smart invite. REQUIRED
          String callback_url : The URL that is notified whenever a change is made. REQUIRED
          Array organizer: An object with recipient details OPTIONAL
                     for example: array(
                         "name" => "Smart invite organizer"
                     )
         */

        $postFields = [
            'event' => $params['event'],
            'smart_invite_id' => $params['smart_invite_id'],
            'callback_url' => $params['callback_url'],
        ];

        if (!empty($params['organizer'])) {
            $postFields['organizer'] = $params['organizer'];
        }

        if (!empty($params['recipients'])) {
            $postFields['recipients'] = $params['recipients'];
        } else {
            $postFields['recipient'] = $params['recipient'];
        }

        return $this->apiKeyHttpPost('/'.self::API_VERSION.'/smart_invites', $postFields);
    }

    public function cancelSmartInvite(array $params): string
    {
        /*
          Array recipient: An object with recipient details REQUIRED
                     for example: array(
                         "email" => "example@example.com"
                     )
          String smart_invite_id: A string representing the id for the smart invite. REQUIRED
         */

        $postFields = [
            'recipient' => $params['recipient'],
            'smart_invite_id' => $params['smart_invite_id'],
            'method' => 'cancel',
        ];

        return $this->apiKeyHttpPost('/'.self::API_VERSION.'/smart_invites', $postFields);
    }

    public function getSmartInvite(string $smart_invite_id, string $recipient_email): string
    {
        /*
          String smart_invite_id: A string representing the id for the smart invite. REQUIRED
          String recipient_email: A string representing the email of the recipient to get status for. REQUIRED
         */

        $urlParams = [
            'smart_invite_id' => $smart_invite_id,
            'recipient_email' => $recipient_email,
        ];

        return $this->apiKeyHttpGet('/'.self::API_VERSION.'/smart_invites', $urlParams);
    }

    public function getAvailabilityRule(string $availability_rule_id): array
    {
        /*
          String availability_rule_id: A string representing the id for the rule. REQUIRED
         */

        return $this->httpGet('/'.self::API_VERSION.'/availability_rules/'.$availability_rule_id);
    }

    public function listAvailabilityRules(): array
    {
        return $this->httpGet('/'.self::API_VERSION.'/availability_rules');
    }

    public function deleteAvailabilityRule(string $availability_rule_id): string
    {
        /*
          String availability_rule_id: A string representing the id for the rule. REQUIRED

          returns true on success, associative array of errors on failure
         */

        return $this->httpDelete('/'.self::API_VERSION.'/availability_rules/'.$availability_rule_id);
    }

    public function createAvailabilityRule(array $rule): array
    {
        /*
          Array rule: An object with an availability rule's details REQUIRED
                 for example: array(
                                "availability_rule_id" => "default",
                                "tzid" => "America/Chicago",
                                "calendar_ids" => array(
                                    "cal_123"
                                ),
                                "weekly_periods" => array(
                                    array(
                                        "day" => "monday",
                                        "start_time" => "09:30",
                                        "end_time" => "12:30"
                                    ),
                                    array(
                                        "day" => "wednesday",
                                        "start_time" => "09:30",
                                        "end_time" => "12:30"
                                    )
                                )
                            )
         */

        $postFields = [
            'availability_rule_id' => $rule['availability_rule_id'],
            'tzid' => $rule['tzid'],
            'calendar_ids' => $rule['calendar_ids'],
            'weekly_periods' => $rule['weekly_periods'],
        ];

        return $this->httpPost('/'.self::API_VERSION.'/availability_rules', $postFields);
    }

    private function apiUrl(string $path): string
    {
        return $this->apiRootUrl.$path;
    }

    private function urlParams(array $params): string
    {
        if (0 == \count($params)) {
            return '';
        }
        $str_params = [];

        foreach ($params as $key => $val) {
            if ('array' == \gettype($val)) {
                foreach ($val as $i => $val) {
                    $str_params[] = $key.'[]='.\urlencode($val[$i]);
                }
            } else {
                $str_params[] = $key.'='.\urlencode($val);
            }
        }

        return '?'.\join('&', $str_params);
    }

    private function getApiKeyAuthHeaders(bool $with_content_headers = false): array
    {
        $headers = [];

        $headers[] = 'Authorization: Bearer '.$this->clientSecret;
        $headers[] = 'Host: '.$this->hostDomain;

        if ($with_content_headers) {
            $headers[] = 'Content-Type: application/json; charset=utf-8';
        }

        return $headers;
    }

    private function getAuthHeaders(bool $with_content_headers = false): array
    {
        $headers = [];

        if (isset($this->accessToken)) {
            $headers[] = 'Authorization: Bearer '.$this->accessToken;
        }
        $headers[] = 'Host: '.$this->hostDomain;

        if ($with_content_headers) {
            $headers[] = 'Content-Type: application/json; charset=utf-8';
        }

        return $headers;
    }

    /**
     * @param string $response
     *
     * @return mixed|string
     */
    private function parsedResponse(string $response)
    {
        $json_decoded = \json_decode($response, true);

        if (JSON_ERROR_NONE != \json_last_error()) {
            return $response;
        }

        return $json_decoded;
    }

    public function handleResponse(string $result, int $status_code)
    {
        if ($status_code >= 200 && $status_code < 300) {
            return $this->parsedResponse($result);
        }

        throw new CronofyException($this->http_codes[$status_code], $status_code, $this->parsedResponse($result));
    }

    private $http_codes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        449 => 'Retry With',
        450 => 'Blocked by Windows Parental Controls',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended',
    ];
}
