<?php
/**
 * Plugin Name: SMSGate Cloud Test
 * Description: Invio SMS di prova da WordPress tramite SMSGate Cloud Server.
 * Version: 1.0.0
 * Author: Gianluca Faella
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMSGate_Cloud_Test_Plugin
{
    const OPTION_NAME = 'smsgate_cloud_test_options';
    const API_ENDPOINT = 'https://api.sms-gate.app/3rdparty/v1/messages';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_admin_page()
    {
        add_management_page(
            'SMSGate Cloud Test',
            'SMSGate Cloud Test',
            'manage_options',
            'smsgate-cloud-test',
            [$this, 'render_page']
        );
    }

    public function register_settings()
    {
        register_setting(
            'smsgate_cloud_test_group',
            self::OPTION_NAME,
            [$this, 'sanitize_options']
        );
    }

    public function sanitize_options($input)
    {
        $old = get_option(self::OPTION_NAME, []);

        $password = isset($input['password'])
            ? sanitize_text_field(wp_unslash($input['password']))
            : '';

        // Se il campo password viene lasciato vuoto, mantengo quella già salvata.
        if ($password === '' && !empty($old['password'])) {
            $password = $old['password'];
        }

        return [
            'username' => isset($input['username'])
                ? sanitize_text_field(wp_unslash($input['username']))
                : ($old['username'] ?? ''),

            'password' => $password,

            'device_id' => isset($input['device_id'])
                ? sanitize_text_field(wp_unslash($input['device_id']))
                : ($old['device_id'] ?? ''),

            'sim_number' => isset($input['sim_number'])
                ? max(1, min(3, absint($input['sim_number'])))
                : 1,

            'ttl' => isset($input['ttl'])
                ? max(60, absint($input['ttl']))
                : 3600,

            'priority' => isset($input['priority'])
                ? max(-128, min(127, intval($input['priority'])))
                : 100,

            'device_active_within' => isset($input['device_active_within'])
                ? absint($input['device_active_within'])
                : 12,

            'skip_phone_validation' => !empty($input['skip_phone_validation']) ? 1 : 0,
        ];
    }

    private function get_options()
    {
        $defaults = [
            'username' => '',
            'password' => '',
            'device_id' => '',
            'sim_number' => 1,
            'ttl' => 3600,
            'priority' => 100,
            'device_active_within' => 12,
            'skip_phone_validation' => 0,
        ];

        return wp_parse_args(get_option(self::OPTION_NAME, []), $defaults);
    }

    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $options = $this->get_options();
        $result = null;

        if (
            isset($_POST['smsgate_send_test']) &&
            check_admin_referer('smsgate_send_test_action', 'smsgate_send_test_nonce')
        ) {
            $to = isset($_POST['smsgate_to'])
                ? sanitize_text_field(wp_unslash($_POST['smsgate_to']))
                : '';

            $message = isset($_POST['smsgate_message'])
                ? sanitize_textarea_field(wp_unslash($_POST['smsgate_message']))
                : '';

            $result = $this->send_sms($to, $message, $options);
        }

        ?>
        <div class="wrap">
            <h1>SMSGate Cloud Server - Test SMS</h1>

            <p>
                Questo plugin invia un SMS di prova usando SMSGate in modalità
                <strong>Cloud Server</strong>.
            </p>

            <p>
                Endpoint usato:
                <code><?php echo esc_html(self::API_ENDPOINT); ?></code>
            </p>

            <?php if ($result): ?>
                <div class="notice <?php echo $result['success'] ? 'notice-success' : 'notice-error'; ?>">
                    <p>
                        <strong><?php echo esc_html($result['message']); ?></strong>
                    </p>

                    <?php if (!empty($result['details'])): ?>
                        <pre style="white-space: pre-wrap; background: #fff; padding: 12px; border: 1px solid #ccd0d4; max-height: 420px; overflow: auto;"><?php
                            echo esc_html($result['details']);
                        ?></pre>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <hr>

            <h2>Configurazione Cloud Server</h2>

            <form method="post" action="options.php">
                <?php settings_fields('smsgate_cloud_test_group'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="smsgate_username">Username Cloud</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="smsgate_username"
                                name="<?php echo esc_attr(self::OPTION_NAME); ?>[username]"
                                value="<?php echo esc_attr($options['username']); ?>"
                                class="regular-text"
                                autocomplete="off"
                                required
                            >
                            <p class="description">
                                Username mostrato nell’app SMSGate nella sezione Cloud Server.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="smsgate_password">Password Cloud</label>
                        </th>
                        <td>
                            <input
                                type="password"
                                id="smsgate_password"
                                name="<?php echo esc_attr(self::OPTION_NAME); ?>[password]"
                                value=""
                                class="regular-text"
                                autocomplete="new-password"
                                <?php echo empty($options['password']) ? 'required' : ''; ?>
                            >

                            <?php if (!empty($options['password'])): ?>
                                <p class="description">
                                    Password già salvata. Lascia vuoto per mantenerla invariata.
                                </p>
                            <?php else: ?>
                                <p class="description">
                                    Password mostrata nell’app SMSGate nella sezione Cloud Server.
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="smsgate_device_id">Device ID opzionale</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="smsgate_device_id"
                                name="<?php echo esc_attr(self::OPTION_NAME); ?>[device_id]"
                                value="<?php echo esc_attr($options['device_id']); ?>"
                                class="regular-text"
                                autocomplete="off"
                            >
                            <p class="description">
                                Lascia vuoto se hai un solo telefono collegato. Usalo se vuoi forzare l’invio da un dispositivo specifico.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="smsgate_sim_number">SIM</label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="smsgate_sim_number"
                                name="<?php echo esc_attr(self::OPTION_NAME); ?>[sim_number]"
                                value="<?php echo esc_attr($options['sim_number']); ?>"
                                min="1"
                                max="3"
                                class="small-text"
                            >
                            <p class="description">
                                Di solito <code>1</code>. Usa <code>2</code> per la seconda SIM.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="smsgate_ttl">TTL</label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="smsgate_ttl"
                                name="<?php echo esc_attr(self::OPTION_NAME); ?>[ttl]"
                                value="<?php echo esc_attr($options['ttl']); ?>"
                                min="60"
                                class="small-text"
                            >
                            <p class="description">
                                Tempo di validità del messaggio in secondi. Default consigliato: <code>3600</code>.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="smsgate_priority">Priorità</label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="smsgate_priority"
                                name="<?php echo esc_attr(self::OPTION_NAME); ?>[priority]"
                                value="<?php echo esc_attr($options['priority']); ?>"
                                min="-128"
                                max="127"
                                class="small-text"
                            >
                            <p class="description">
                                Valore da <code>-128</code> a <code>127</code>. Per test puoi usare <code>100</code>.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="smsgate_device_active_within">Device attivo entro</label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="smsgate_device_active_within"
                                name="<?php echo esc_attr(self::OPTION_NAME); ?>[device_active_within]"
                                value="<?php echo esc_attr($options['device_active_within']); ?>"
                                min="0"
                                class="small-text"
                            >
                            <p class="description">
                                In ore. Con <code>12</code> SMSGate prova a usare dispositivi attivi nelle ultime 12 ore.
                                Usa <code>0</code> per disattivare questo filtro.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Validazione numero</th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[skip_phone_validation]"
                                    value="1"
                                    <?php checked($options['skip_phone_validation'], 1); ?>
                                >
                                Disabilita validazione E.164 lato SMSGate
                            </label>
                            <p class="description">
                                Normalmente lascia disattivato e usa numeri in formato internazionale, ad esempio <code>+393331234567</code>.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Salva configurazione'); ?>
            </form>

            <hr>

            <h2>Invio SMS di prova</h2>

            <form method="post">
                <?php wp_nonce_field('smsgate_send_test_action', 'smsgate_send_test_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="smsgate_to">Numero destinatario</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="smsgate_to"
                                name="smsgate_to"
                                class="regular-text"
                                placeholder="+393331234567"
                                required
                            >
                            <p class="description">
                                Usa formato internazionale E.164, ad esempio <code>+393331234567</code>.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="smsgate_message">Messaggio</label>
                        </th>
                        <td>
                            <textarea
                                id="smsgate_message"
                                name="smsgate_message"
                                rows="4"
                                class="large-text"
                                required
                            >Invio di prova da WordPress tramite SMSGate Cloud Server</textarea>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Invia SMS di prova', 'primary', 'smsgate_send_test'); ?>
            </form>
        </div>
        <?php
    }

    private function send_sms($to, $message, $options)
    {
        if (empty($options['username']) || empty($options['password'])) {
            return [
                'success' => false,
                'message' => 'Username o password SMSGate mancanti.',
                'details' => '',
            ];
        }

        if (empty($to) || empty($message)) {
            return [
                'success' => false,
                'message' => 'Numero destinatario o messaggio mancanti.',
                'details' => '',
            ];
        }

        $payload = [
            'textMessage' => [
                'text' => $message,
            ],
            'phoneNumbers' => [
                $to,
            ],
            'simNumber' => absint($options['sim_number']),
            'ttl' => absint($options['ttl']),
            'priority' => intval($options['priority']),
        ];

        if (!empty($options['device_id'])) {
            $payload['deviceId'] = $options['device_id'];
        }

        $query_args = [
            'skipPhoneValidation' => !empty($options['skip_phone_validation']) ? 'true' : 'false',
        ];

        if (!empty($options['device_active_within'])) {
            $query_args['deviceActiveWithin'] = absint($options['device_active_within']);
        }

        $url = add_query_arg($query_args, self::API_ENDPOINT);

        $auth = base64_encode($options['username'] . ':' . $options['password']);

        $response = wp_remote_post($url, [
            'timeout' => 30,
            'redirection' => 3,
            'sslverify' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $auth,
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Errore di connessione verso SMSGate Cloud Server.',
                'details' => $response->get_error_message() . "\n\n" .
                    'Verifica che il server WordPress possa uscire verso https://api.sms-gate.app sulla porta 443.',
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $decoded = json_decode($body, true);
        $pretty_body = $body;

        if (json_last_error() === JSON_ERROR_NONE) {
            $pretty_body = wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $success = $status_code >= 200 && $status_code < 300;

        return [
            'success' => $success,
            'message' => $success
                ? 'Richiesta inviata correttamente a SMSGate Cloud Server.'
                : 'SMSGate Cloud Server ha risposto con errore HTTP ' . $status_code . '.',
            'details' =>
                "HTTP Status: {$status_code}\n\n" .
                "URL:\n{$url}\n\n" .
                "Risposta SMSGate:\n{$pretty_body}\n\n" .
                "Payload inviato:\n" .
                wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];
    }
}

new SMSGate_Cloud_Test_Plugin();
