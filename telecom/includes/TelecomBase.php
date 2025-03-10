<?php
require_once 'BaseUsers.php';

class TelecomBase {
    private $data_manager;
    private $store_report;

    public function __construct() {
        $this->data_manager = new BaseUsers('data/telecomusers.csv');
        $this->store_report = new BaseUsers('data/telecomreport.csv');
    }

    public function report() {
        $this->store_report->rewrite_users([["Name", "Tab name", "Remaining", "Used", "Offer Name", "Renewal date", "Remaining Days"]]);

        $results = [];
        foreach ($this->get_users() as $user) {
            try {
                $data = $this->te_login(...$user);
                if (!is_array($data)) {
                    throw new Exception("Invalid data returned from te_login for user: " . implode(", ", $user));
                }
                $this->store_report->append_user(array_values($data));
                $results[] = $this->form_result_text($data);
            } catch (Exception $ex) {
                ERROR($ex->getMessage());
            }
        }
        return $results;
    }

    public function login($title, $name, $password) {
        try {
            $data = $this->te_login($title, $name, $password);
            if (!is_array($data)) {
                throw new Exception("Invalid data returned from te_login");
            }
            $this->store_report->rewrite_users([array_values($data)]);
            return $this->form_result_text($data);
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    private function form_result_text($data) {
        $text = "";
        foreach ($data as $key => $value) {
            if ($key === "Name" || $key === "Tab name") {
                $text .= $value . "\n";
            } else {
                $text .= "$key: $value\n";
            }
        }
        return $text;
    }

    private function needed_data($data) {
        return [
            "Name" => $data[0] ?? "N/A",
            "Tab name" => $data[1]["tabName"] ?? "N/A",
            "Remaining" => $data[1]["remain"] ?? "N/A",
            "Used" => $data[1]["used"] ?? "N/A",
            "Offer EN Name" => $data[2]["offerEnName"] ?? "N/A",
            "Renewal date" => $data[3] ?? "N/A",
            "Remaining Days" => $data[2]["remainingDaysForRenewal"] ?? "N/A"
        ];
    }

    private function get_users() {
        return $this->data_manager->read_users();
    }

    private function te_login($k, $name, $password) {
        try {
            $auth_url = 'https://my.te.eg/echannel/service/besapp/base/rest/busiservice/v1/auth/userAuthenticate';
            $headers = [
                'Accept: application/json',
                'Content-Type: application/json',
                'DNT: 1',
                'channelId: 702',
                'isSelfcare: true'
            ];
            $json_data = json_encode([
                'acctId' => $name,
                'password' => $password,
                'appLocale' => 'en-US',
                'isSelfcare' => 'Y',
                'isMobile' => 'N',
                'recaptchaToken' => ''
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $auth_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("CURL Error: " . curl_error($ch));
            }
            curl_close($ch);

            $auth_data = json_decode($response, true);
            if (empty($auth_data) || !isset($auth_data['body']['token'])) {
                throw new Exception("Authentication failed. Response: " . print_r($auth_data, true));
            }

            $token = $auth_data['body']['token'];
            $uToken = $auth_data['body']['uToken'];
            $subscriberId = $auth_data['body']['subscriber']['subscriberId'];

            $headers = [
                'Accept: application/json',
                'Content-Type: application/json',
                'csrftoken: ' . $token,
                'DNT: 1',
                'channelId: 702',
                'isSelfcare: true'
            ];
            $cookies = ['indiv_login_token=' . $uToken];

            // Fetch usage data
            $usage_url = 'https://my.te.eg/echannel/service/besapp/base/rest/busiservice/cz/cbs/bb/queryFreeUnit';
            $usage_data = json_encode(['subscriberId' => $subscriberId, 'needQueryPoint' => true]);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $usage_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $usage_data);
            curl_setopt($ch, CURLOPT_COOKIE, implode(';', $cookies));

            $usage_response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("CURL Error: " . curl_error($ch));
            }
            curl_close($ch);

            $usage_data = json_decode($usage_response, true);
            if (empty($usage_data) || !isset($usage_data['body'])) {
                throw new Exception("Failed to fetch usage data. Response: " . print_r($usage_data, true));
            }

            // Fetch offers data
            $offers_url = 'https://my.te.eg/echannel/service/besapp/base/rest/busiservice/cz/v1/auth/getSubscribedOfferings';
            $offers_data = json_encode([
                'msisdn' => $name,
                'numberServiceType' => 'FBB',
                'groupId' => ''
            ]);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $offers_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $offers_data);
            curl_setopt($ch, CURLOPT_COOKIE, implode(';', $cookies));

            $offers_response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception("CURL Error: " . curl_error($ch));
            }
            curl_close($ch);

            $offers_data = json_decode($offers_response, true);
            if (empty($offers_data) || !isset($offers_data['body']['offeringList'])) {
                throw new Exception("Failed to fetch offers data. Response: " . print_r($offers_data, true));
            }

            $main_offer = null;
            foreach ($offers_data['body']['offeringList'] as $offer) {
                if ($offer['main']) {
                    $main_offer = $offer;
                    break;
                }
            }

            if (!$main_offer) {
                throw new Exception("No main offer found.");
            }

            $renewal_date = date('Y-m-d H:i:s', $main_offer['renewalDate'] / 1000);
            return [
                "Name" => $k,
                "Tab name" => $usage_data['body'][0]['tabName'],
                "Remaining" => $usage_data['body'][0]['remain'],
                "Used" => $usage_data['body'][0]['used'],
                "Offer EN Name" => $main_offer['offerEnName'],
                "Renewal date" => $renewal_date,
                "Remaining Days" => $main_offer['remainingDaysForRenewal']
            ];
        } catch (Exception $ex) {
            ERROR($ex->getMessage());
            return [];
        }
    }
}
?>