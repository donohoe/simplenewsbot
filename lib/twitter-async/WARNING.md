

I fixed a bug in this... (@donohoe)

My full modified httpPost function now reads as:


  protected function httpPost($url, $params = null, $isMultipart)
  {
    $this->addDefaultHeaders($url, $params['oauth']);
    $ch = $this->curlInit($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    // php's curl extension automatically sets the content type
    // based on whether the params are in string or array form

    /* Manual Edit as per: https://github.com/jmathai/twitter-async/issues/186 */

    if ($isMultipart) {
        if (isset($params['request']['status'])) {
            $params['request']['status']=urldecode($params['request']['status']);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params['request']);
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->buildHttpQueryRaw($params['request']));
    }

    /* End Edit */

    $resp = $this->executeCurl($ch);
    $this->emptyHeaders();

    return $resp;
  }



