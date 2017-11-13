<?php

namespace MotaMonteiro\Sefaz\Portal\Helpers;


use Illuminate\Support\Facades\Cache;
use MotaMonteiro\Helpers\UtilHelper;

class UsuarioLogadoHelper
{
    public $helper;
    public $tokenKey;
    public $tokenValue;
    public $numCpf;
    public $nmeLogin;
    public $nmeUsuario;
    public $nmeEmail;
    public $nmeSetor;
    public $numCnpjOrgao;
    public $nmeOrgao;
    public $numValidadeEmMinutos;
    public $datValidade;
    public $sistemasPortal;
    public $permissoesPortal;

    function __construct($numValidadeEmMinutos = 5)
    {
        $this->helper = new UtilHelper();
        $this->tokenKey = $this->getTokenKey();
        $this->tokenValue = $this->getTokenValue();
        $this->numCpf = '';
        $this->nmeLogin = '';
        $this->nmeUsuario = '';
        $this->nmeEmail = '';
        $this->nmeSetor = '';
        $this->numCnpjOrgao = '';
        $this->nmeOrgao = '';
        $this->numValidadeEmMinutos = $numValidadeEmMinutos;
        $this->datValidade = '';
        $this->sistemasPortal = [];
        $this->permissoesPortal = [];

    }

    private function getTokenKey()
    {
        return (\request()->header('Authorization') != '') ? 'Authorization' : config('sistema.portal_api.token_key');
    }

    private function getTokenValue()
    {
        $token = \request()->header('Authorization') ?? '';
        if ($token == '') {
            $token = \request()->header($this->tokenKey) ?? '';
            if ($token == '') {
                $token = $_COOKIE[config('sistema.portal.nome_cookie')] ?? '';
            }
        }
        return $token;
    }

    public function validarUsuarioLogado()
    {
        if (
            (!$this->tokenValue)
            || (!$this->helper->validarCpf($this->numCpf))
            || ($this->helper->compararDataHoraFormatoBr($this->datValidade, '<', date('d/m/Y H:i:s')))
        ) {
            return false;
        }
        return true;
    }

    /**
     * @return $this
     */
    public function getUsuarioLogado($codSistema = '', $codModulo = '')
    {

        if ($this->tokenValue != '') {

            $cacheKey = 'usuario_'.$this->tokenValue;
            $cacheKey .= ($codSistema != '') ? '_cod_sistema_' . strtolower($codSistema) : '';
            $cacheKey .= ($codModulo != '') ? '_cod_modulo_' . strtolower($codModulo) : '';

            $this->getUsuarioLogadoDoCache($cacheKey);

            if (!$this->validarUsuarioLogado()) {

                return Cache::remember($cacheKey, $this->numValidadeEmMinutos, function () use ($codSistema, $codModulo) {

                    if ($codSistema != '') {
                        $nmeRota = 'permissao/sistema/'.strtolower($codSistema);
                        $nmeRota .= ($codModulo != '') ? '/modulo/' . strtolower($codModulo) : '';
                    } else {
                        $nmeRota = 'permissao/raiz/sistema';
                    }
                    $this->getUsuarioLogadoDaApi($nmeRota);
                    return $this;
                });
            }
        }

        return $this;
    }

    private function getUsuarioLogadoDoCache($cacheKey)
    {
        $usuarioLogadoCache = Cache::get($cacheKey);

        if ($usuarioLogadoCache instanceof $this) {
            $this->preencherUsuarioLogadoDoCache($usuarioLogadoCache);
        }

        return $this;
    }

    private function getUsuarioLogadoDaApi($nmeRota)
    {
        if (!empty($this->tokenKey) && !empty($this->tokenValue)) {

            $api = new ApiHelper(config('sistema.portal_api.url'), $this->tokenKey, $this->tokenValue);

            $usuarioLogadoApi = $api->chamaApi($nmeRota, 'GET');

            if (!$api->existeMsgErroApi($usuarioLogadoApi)) {

                $this->preencherUsuarioLogadoDaApi($usuarioLogadoApi);

            } elseif ($usuarioLogadoApi['message'] == 'token_expired') {

                $token = $api->chamaApi('autenticacao/refreshToken', 'GET');

                if (!$api->existeMsgErroApi($token)) {
                    $api->setTokenValue($token['token']);

                    $usuarioLogadoApi = $api->chamaApi('permissao/raiz/sistema', 'GET');

                    if (!$api->existeMsgErroApi($usuarioLogadoApi)) {

                        $this->preencherUsuarioLogadoDaApi($usuarioLogadoApi);
                    }
                }
            }
        }

        return $this;
    }

    private function preencherUsuarioLogadoDaApi($usuarioLogadoApi)
    {
        if (!isset($usuarioLogadoApi['permissao'])) {

            $this->numCpf = $usuarioLogadoApi['numCpf'];
            $this->nmeLogin = $usuarioLogadoApi['nmeLogin'];
            $this->nmeUsuario = $usuarioLogadoApi['nmeUsuario'];
            $this->nmeEmail = $usuarioLogadoApi['nmeEmail'];
            $this->nmeSetor = $usuarioLogadoApi['nmeSetor'];
            $this->numCnpjOrgao = $usuarioLogadoApi['numCnpjOrgao'];
            $this->nmeOrgao = '';
            $this->sistemasPortal = $usuarioLogadoApi['sistemas'] ?? [];
            $this->datValidade = $this->helper->somarDataHoraFormatoBr(date('d/m/Y H:i:s'), 0, 0, 0, 0, $this->numValidadeEmMinutos, 0);

        } else {

            $this->numCpf = $usuarioLogadoApi['usuario']['numCpf'];
            $this->nmeLogin = $usuarioLogadoApi['usuario']['nmeLogin'];
            $this->nmeUsuario = $usuarioLogadoApi['usuario']['nmeUsuario'];
            $this->nmeEmail = $usuarioLogadoApi['usuario']['nmeEmail'];
            $this->nmeSetor = $usuarioLogadoApi['usuario']['nmeSetor'];
            $this->numCnpjOrgao = $usuarioLogadoApi['usuario']['numCnpjOrgao'];
            $this->nmeOrgao = '';
            $this->sistemasPortal = [];
            $this->permissoesPortal = $usuarioLogadoApi['permissao'] ?? [];
            $this->datValidade = $this->helper->somarDataHoraFormatoBr(date('d/m/Y H:i:s'), 0, 0, 0, 0, $this->numValidadeEmMinutos, 0);
        }
    }

    private function preencherUsuarioLogadoDoCache($usuarioLogadoCache)
    {
        $this->tokenKey = $usuarioLogadoCache->tokenKey;
        $this->tokenValue = $usuarioLogadoCache->tokenValue;
        $this->numCpf = $usuarioLogadoCache->numCpf;
        $this->nmeLogin = $usuarioLogadoCache->nmeLogin;
        $this->nmeUsuario = $usuarioLogadoCache->nmeUsuario;
        $this->nmeEmail = $usuarioLogadoCache->nmeEmail;
        $this->nmeSetor = $usuarioLogadoCache->nmeSetor;
        $this->numCnpjOrgao = $usuarioLogadoCache->numCnpjOrgao;
        $this->nmeOrgao = $usuarioLogadoCache->nmeOrgao;
        $this->sistemasPortal = $usuarioLogadoCache->sistemasPortal;
        $this->permissoesPortal = $usuarioLogadoCache->permissoesPortal;
        $this->datValidade = $usuarioLogadoCache->datValidade;
    }

}