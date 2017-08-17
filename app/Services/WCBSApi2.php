<?php namespace App\Services;

use GuzzleHttp\Client;

class WCBSApi2
{
    const endpoint = 'https://3sys.isdedu.de/Wcbs.API/api/';
    protected $client_id;
    protected $client_secret;
    protected $bearer_token;
    public function __construct($client_id, $client_secret)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->authorize();
    }

    public function authorize()
    {
        $client = new Client([
            'headers' => [
                'User-Agent' => 'isd-sync-services'
            ]
        ]);
        $body = ['form_params' => [
            'grant_type' => 'client_credentials',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
        ]];
        $response = $client->post(self::endpoint . 'token', $body);
        $response = json_decode($response->getBody()->getContents());
        $this->bearer_token = $response->access_token;
    }

    public function client()
    {
        if(!$this->bearer_token) {
            return 'No Bearer Token is Present';
        }
        return new Client([
            'headers' => [
                'Authorization' => 'Bearer ' . $this->bearer_token,
                'User-Agent' => 'isd-sync-services',
            ]
        ]);
    }

    public function getUrl($url)
    {
        return $this->client()->get($url);
    }

    public function getRequest($endpoint)
    {
        return $this->getUrl(self::endpoint . $endpoint);
    }
}