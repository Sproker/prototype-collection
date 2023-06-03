<?php

namespace Drupal\h5p_analytics\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\h5p_analytics\LrsService;
use Drupal\h5p_analytics\LrsServiceInterface;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ModuleConfigurationForm.
 */
class ModuleConfigurationForm extends ConfigFormBase
{
    /**
     * Config settings
     * @var string
     */
    const SETTINGS = LrsService::SETTINGS;

    /**
     * Messenger service
     *
     * @var MessengerInterface
     */
    protected $messenger;

    /**
     * LRS service
     *
     * @var LrsServiceInterface
     */
    protected $lrs;

    /**
     * Renderer service
     *
     * @var RendererInterface
     */
    protected $renderer;

    public static function create(ContainerInterface $container)
    {
        $instance = parent::create($container);
        $instance->messenger = $container->get('messenger');
        $instance->lrs = $container->get('h5p_analytics.lrs');
        $instance->renderer = $container->get('renderer');

        return $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'h5p_analytics_module_configuration_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config(static::SETTINGS);

        $form['connection_test_messages'] = [
            '#type' => 'container',
            '#attributes' => [
                'id' => 'connection-test-messages',
            ],
        ];

        $form['lrs'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('LRS'),
            '#description' => $this->t('LRS Settings'),
            '#weight' => '0',
        ];
        $form['lrs']['xapi_endpoint'] = [
            '#type' => 'url',
            '#title' => $this->t('xAPI Endpoint'),
            '#description' => $this->t('xAPI Endpoint URL (no trailing slash)'),
            '#weight' => '0',
            '#size' => 64,
            '#default_value' => $config->get('xapi_endpoint'),
        ];
        $form['lrs']['key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Key'),
            '#description' => $this->t('LRS Client Key'),
            '#maxlength' => 64,
            '#size' => 64,
            '#weight' => '1',
            '#default_value' => $config->get('key'),
        ];
        $form['lrs']['secret'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Secret'),
            '#description' => $this->t('LRS Client Secret'),
            '#maxlength' => 64,
            '#size' => 64,
            '#weight' => '2',
            '#default_value' => $config->get('secret'),
        ];
        $form['lrs']['authorization_header'] = [
            '#type' => 'textfield',
            '#title' => $this->t('HTTP request authentication'),
            '#description' => $this->t('LRS client Basic auth value'),
            '#size' => 64,
            '#weight' => '2',
            '#default_value' => $config->get('authorization_header'),
        ];
        $form['lrs']['batch_size'] = [
            '#type' => 'number',
            '#title' => $this->t('Batch size'),
            '#description' => $this->t('Size of the statements batch to be sent to LRS.'),
            '#min' => 1,
            '#max' => 1000,
            '#step' => 1,
            '#size' => 64,
            '#weight' => '3',
            '#default_value' => $config->get('batch_size'),
        ];
        $form['lrs']['timeout'] = [
            '#type' => 'number',
            '#title' => $this->t('Timeout'),
            '#description' => $this->t('HTTP request timeout value in seconds used for sending data to LRS.'),
            '#min' => 30,
            '#step' => 1,
            '#size' => 64,
            '#weight' => '3',
            '#default_value' => $config->get('timeout'),
        ];

        $build = parent::buildForm($form, $form_state);

        $build['actions']['test_connection'] = [
            '#type' => 'button',
            '#name' => 'test_connection',
            '#ajax' => [
                'callback' => [$this, 'testConnectionCallback'],
                'effect' => 'fade',
            ],
            '#value' => $this->t('Test LRS connection'),
            '#disabled' => !($config->get('xapi_endpoint') && $config->get('key') && $config->get('secret')),
        ];

        return $build;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValues();
        $this->configFactory->getEditable(static::SETTINGS)
            ->set('xapi_endpoint', $values['xapi_endpoint'])
            ->set('key', $values['key'])
            ->set('secret', $values['secret'])
            ->set('authorization_header', $values['authorization_header'])
            ->set('batch_size', $values['batch_size'])
            ->set('timeout', $values['timeout'])
            ->save();
        parent::submitForm($form, $form_state);
    }

    /**
     * AJAX callback for testing LRS connection, displays status messages.
     *
     * @param array $form
     *   Form
     * @param FormStateInterface $form_state
     *   Form state
     *
     * @return AjaxResponse
     *   AjaxResponse with command to display status messages
     */
    public function testConnectionCallback(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValues();

        try {
            $response = $this->lrs->makeStatementsHttpRequest($values['xapi_endpoint'], $values['key'], $values['secret'], []);
            $this->messenger->addMessage($this->t('Connection to LRS service is working well. Service responded with code %status and message %message.', ['%status' => $response->getStatusCode(), '%message' => $response->getReasonPhrase()]), 'status');
        } catch (RequestException $e) {
            $this->messenger->addMessage($this->t('Service responded with code %code and message %message.', ['%code' => $e->getCode(), '%message' => $e->hasResponse() ? $e->getResponse()->getReasonPhrase() : '']), 'warning');
            $this->messenger->addMessage($e->getMessage(), 'error');
        } catch (Exception $e) {
            $this->messenger->addMessage($e->getMessage(), 'error');
        }

        $response = new AjaxResponse();
        $status_messages = array('#type' => 'status_messages');
        $messages = $this->renderer->renderRoot($status_messages);

        if ($messages) {
            $response->addCommand(new HtmlCommand('#connection-test-messages', $messages));
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return [
            static::SETTINGS,
        ];
    }

}
