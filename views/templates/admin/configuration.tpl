{*
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *}

{if isset($message) && $message}
    <div class="alert alert-{$message.type}">
        {$message.content}
    </div>
{/if}

<form action="" method="post">
    <div class="panel">
        <div class="panel-heading">
            <i class="icon icon-cogs"></i>
            <span>{l s='Configurazione' mod='mpexportdocuments'}</span>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-6 col-xs-9">
                    <fieldset class="mb-4">
                        <legend class="legend">{l s='Cliente' mod='mpexportdocuments'}</legend>
                        <div class="form-group">
                            <label for="CUSTOMER_PREFIX">{l s='Prefisso cliente' mod='mpexportdocuments'}</label>
                            <div class="input-group">
                                <span class="input-group-addon"><i class="icon icon-chevron-right"></i></span>
                                <input type="text" id="CUSTOMER_PREFIX" name="CUSTOMER_PREFIX" class="form-control" value="{$CUSTOMER_PREFIX}">
                                <span class="input-group-addon">{l s='[A-Z]' mod='mpexportdocuments'}</span>
                            </div>
                        </div>
                    </fieldset>
                    <fieldset class="mb-4">
                        <legend class="legend">{l s='Tipo di documento' mod='mpexportdocuments'}</legend>
                        <div class="form-group">
                            <label for="TYPE_ORDER">{l s='Ordine' mod='mpexportdocuments'}</label>
                            <div class="input-group">
                                <span class="input-group-addon"><i class="icon icon-chevron-right"></i></span>
                                <input type="text" id="TYPE_ORDER" name="TYPE_ORDER" class="form-control" value="{$TYPE_ORDER}">
                                <span class="input-group-addon">{l s='[0-9]' mod='mpexportdocuments'}</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="TYPE_INVOICE">{l s='Fattura' mod='mpexportdocuments'}</label>
                            <div class="input-group">
                                <span class="input-group-addon"><i class="icon icon-chevron-right"></i></span>
                                <input type="text" id="TYPE_INVOICE" name="TYPE_INVOICE" class="form-control" value="{$TYPE_INVOICE}">
                                <span class="input-group-addon">{l s='[0-9]' mod='mpexportdocuments'}</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="TYPE_RETURN">{l s='Reso da cliente' mod='mpexportdocuments'}</label>
                            <div class="input-group">
                                <span class="input-group-addon"><i class="icon icon-chevron-right"></i></span>
                                <input type="text" id="TYPE_RETURN" name="TYPE_RETURN" class="form-control" value="{$TYPE_RETURN}">
                                <span class="input-group-addon">{l s='[0-9]' mod='mpexportdocuments'}</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="TYPE_SLIP">{l s='Reso da fornitore' mod='mpexportdocuments'}</label>
                            <div class="input-group">
                                <span class="input-group-addon"><i class="icon icon-chevron-right"></i></span>
                                <input type="text" id="TYPE_SLIP" name="TYPE_SLIP" class="form-control" value="{$TYPE_SLIP}">
                                <span class="input-group-addon">{l s='[0-9]' mod='mpexportdocuments'}</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="TYPE_DELIVERY">{l s='Nota Ordine' mod='mpexportdocuments'}</label>
                            <div class="input-group">
                                <span class="input-group-addon"><i class="icon icon-chevron-right"></i></span>
                                <input type="text" id="TYPE_DELIVERY" name="TYPE_DELIVERY" class="form-control" value="{$TYPE_DELIVERY}">
                                <span class="input-group-addon">{l s='[0-9]' mod='mpexportdocuments'}</span>
                            </div>
                        </div>
                    </fieldset>
                    <fieldset class="mb-4">
                        <legend class="legend">{l s='Modulo di pagamento in contanti' mod='mpexportdocuments'}</legend>
                        <div class="form-group">
                            <label for="PAYMENT_SELECTED">{l s='Seleziona i moduli di pagamento in contanti' mod='mpexportdocuments'}</label>
                            <div class="input-group">
                                <span class="input-group-addon"><i class="icon icon-chevron-right"></i></span>
                                <select id="PAYMENT_SELECTED" name="PAYMENT_SELECTED[]" class="form-control chosen" multiple>
                                    {foreach $PAYMENT_MODULES as $payment}
                                        <option value="{$payment['id_module']}" {if in_array($payment['id_module'],$PAYMENT_SELECTED)}selected{/if}>{$payment['name']}</option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>
                    </fieldset>
                    <fieldset class="mb-4">
                        <legend class="legend">{l s='Nome del file di esportazione' mod='mpexportdocuments'}</legend>
                        <div class="form-group">
                            <label for="EXPORT_FILE_NAME">{l s='Nome del file di esportazione' mod='mpexportdocuments'}</label>
                            <div class="input-group">
                                <span class="input-group-addon"><i class="icon icon-chevron-right"></i></span>
                                <input type="text" id="EXPORT_FILE_NAME" name="EXPORT_FILE_NAME" class="form-control" value="{$EXPORT_FILE_NAME}">
                                <span class="input-group-addon">{l s='es. export, al file sarà aggiunta la data' mod='mpexportdocuments'}</span>
                            </div>
                        </div>
                    </fieldset>
                </div>
            </div>

        </div>
        <div class="panel-footer">
            <button type="submit" name="submitConfiguration" class="btn btn-primary pull-right">{l s='Salva' mod='mpexportdocuments'}</button>
        </div>
    </div>
</form>