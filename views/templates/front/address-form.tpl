{extends file='customer/page.tpl'}

{block name='page_title'}
    {if $action == 'add'}
        {l s='Aggiungi indirizzo' mod='mpcustomerinvoice'}
    {else}
        {l s='Modifica indirizzo' mod='mpcustomerinvoice'}
    {/if}
{/block}

{block name='page_content'}
    <style>
        .mp-address-form {
            max-width: 920px;
            margin: 0 auto;
        }

        .mp-address-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 10px 35px rgba(27, 46, 75, .1);
            overflow: hidden;
        }

        .mp-address-card__header {
            background: linear-gradient(135deg, #183b66, #2469a8);
            color: #fff;
            padding: 1.5rem 1.75rem;
        }

        .mp-address-card__body {
            padding: 1.75rem;
        }

        .mp-address-card .form-control {
            border-radius: 8px;
            min-height: 42px;
        }

        .mp-invoice-toggle {
            align-items: center;
            background: #eff7ff;
            border: 2px solid #b9d9f5;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            gap: 1rem;
            margin: 1.5rem 0;
            padding: 1rem 1.25rem;
            transition: .2s ease;
        }

        .mp-invoice-toggle.is-active {
            background: #e6f6ed;
            border-color: #51a974;
        }

        .mp-invoice-toggle input {
            height: 22px;
            margin: 0;
            width: 22px;
        }

        .mp-invoice-section {
            background: #f8fbfe;
            border-radius: 12px;
            display: none;
            padding: 1.5rem;
        }

        .mp-invoice-section.is-visible {
            display: block;
        }

        .mp-invoice-field {
            display: none;
        }

        .mp-invoice-field.is-visible {
            display: block;
        }

        .mp-form-feedback {
            display: none;
            margin-bottom: 1rem;
        }
    </style>
    <form id="mp-address-form" action="{$form_action}" method="post" class="mp-address-form">
        <div class="card mp-address-card">
            <div class="mp-address-card__header">
                <h2 class="h4 mb-1 text-white">{if $action == 'add'}{l s='Nuovo indirizzo' mod='mpcustomerinvoice'}{elseif $action == 'view'}{l s='Indirizzo di fatturazione' mod='mpcustomerinvoice'}{else}{l s='Modifica indirizzo' mod='mpcustomerinvoice'}{/if}</h2>
                <p class="mb-0 text-white">{l s='Inserisci i dati di consegna e, se necessario, quelli di fatturazione.' mod='mpcustomerinvoice'}</p>
            </div>
            <div class="mp-address-card__body">
                <div id="mp-form-feedback" class="alert alert-danger mp-form-feedback" role="alert"></div>
                <fieldset{if $is_invoice_address} disabled{/if}>
                    <div class="row">
                        <div class="col-md-4 form-group"><label for="alias">{l s='Alias indirizzo' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="alias" name="alias" value="{$address->alias|escape:'htmlall':'UTF-8'}" required></div>
                        <div class="col-md-4 form-group"><label for="firstname">{l s='Nome' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="firstname" name="firstname" value="{$address->firstname|escape:'htmlall':'UTF-8'}" required></div>
                        <div class="col-md-4 form-group"><label for="lastname">{l s='Cognome' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="lastname" name="lastname" value="{$address->lastname|escape:'htmlall':'UTF-8'}" required></div>
                        <div class="col-md-8 form-group"><label for="address1">{l s='Indirizzo' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="address1" name="address1" value="{$address->address1|escape:'htmlall':'UTF-8'}" required></div>
                        <div class="col-md-4 form-group"><label for="address2">{l s='Interno, scala o piano' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="address2" name="address2" value="{$address->address2|escape:'htmlall':'UTF-8'}"></div>
                        <div class="col-md-4 form-group"><label for="id_country">{l s='Nazione' mod='mpcustomerinvoice'}</label><select class="form-control chosen-select" id="id_country" name="id_country" required>
                                <option value="">{l s='Seleziona una nazione' mod='mpcustomerinvoice'}</option>
                                {foreach from=$countries item=country}<option value="{$country.id_country}" 
                                    {if $address->id_country == $country.id_country} selected 
                                    {/if}>{$country.name|escape:'htmlall':'UTF-8'}</option>
                                {/foreach}
                            </select></div>
                        <div class="col-md-4 form-group" id="state-group" {if !$country_has_states} style="display:none" {/if}><label for="id_state">{l s='Provincia' mod='mpcustomerinvoice'}</label><select class="form-control chosen-select" id="id_state" name="id_state" {if $country_has_states} required{/if}>
                                <option value="">{l s='Seleziona una provincia' mod='mpcustomerinvoice'}</option>
                                {foreach from=$states item=state}<option value="{$state.id_state}" 
                                    {if $address->id_state == $state.id_state} selected 
                                    {/if}>{$state.name|escape:'htmlall':'UTF-8'}
                                        {if $state.iso_code} ({$state.iso_code|escape:'htmlall':'UTF-8'})
                                        {/if}</option>
                                {/foreach}
                            </select></div>
                        <div class="col-md-4 form-group"><label for="city">{l s='Città' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="city" name="city" value="{$address->city|escape:'htmlall':'UTF-8'}" required></div>
                        <div class="col-md-4 form-group"><label for="postcode">{l s='CAP' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="postcode" name="postcode" value="{$address->postcode|escape:'htmlall':'UTF-8'}" required></div>
                        <div class="col-md-4 form-group"><label for="phone">{l s='Telefono' mod='mpcustomerinvoice'}</label><input type="tel" class="form-control" id="phone" name="phone" value="{$address->phone|escape:'htmlall':'UTF-8'}"></div>
                        <div class="col-md-4 form-group"><label for="phone_mobile">{l s='Cellulare' mod='mpcustomerinvoice'}</label><input type="tel" class="form-control" id="phone_mobile" name="phone_mobile" value="{$address->phone_mobile|escape:'htmlall':'UTF-8'}"></div>
                    </div>
                    </fieldset>
                    <input type="hidden" name="id_address" value="{$address->id_address}">
                    {if $can_request_invoice}
                        <input type="hidden" name="want_invoice" value="0">
                        <label class="mp-invoice-toggle" for="want_invoice">
                            <input type="checkbox" id="want_invoice" name="want_invoice" value="1">
                            <span>
                                <strong>{l s='Desidero ricevere la fattura' mod='mpcustomerinvoice'}</strong>
                                <br>
                                <small>{l s='Aggiungi i dati necessari per la fatturazione elettronica.' mod='mpcustomerinvoice'}</small>
                            </span>
                        </label>
                        <section id="mp-invoice-section" class="mp-invoice-section">
                            <h3 class="h5 mb-3">{l s='Dati di fatturazione' mod='mpcustomerinvoice'}</h3>
                            <div class="row">
                                <div class="col-md-4 form-group"><label for="invoice_type">{l s='Tipo intestatario' mod='mpcustomerinvoice'}</label><select class="form-control" id="invoice_type" name="invoice_type">
                                        <option value="">{l s='Seleziona il tipo' mod='mpcustomerinvoice'}</option>
                                        <option value="PRIVATO">{l s='Privato' mod='mpcustomerinvoice'}</option>
                                        <option value="PARTITA_IVA">{l s='Partita IVA' mod='mpcustomerinvoice'}</option>
                                        <option value="ENTE">{l s='Ente pubblico' mod='mpcustomerinvoice'}</option>
                                    </select></div>
                                <div class="col-md-8 form-group"><label for="company">{l s='Intestazione fattura' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="company" name="company" maxlength="255"></div>
                                <div class="col-md-4 form-group mp-invoice-field" data-types="PARTITA_IVA"><label for="vat_number">{l s='Partita IVA' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="vat_number" name="vat_number" maxlength="16"></div>
                                <div class="col-md-4 form-group mp-invoice-field" data-types="PRIVATO,ENTE"><label for="dni">{l s='Codice fiscale' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="dni" name="dni" maxlength="16"></div>
                                <div class="col-md-4 form-group mp-invoice-field" data-types="ENTE"><label for="cuu">{l s='Codice CUU' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="cuu" name="cuu" maxlength="6"></div>
                                <div class="col-md-4 form-group mp-invoice-field" data-types="PARTITA_IVA"><label for="sdi">{l s='Codice SDI' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="sdi" name="sdi" maxlength="7"></div>
                                <div class="col-md-4 form-group mp-invoice-field" data-types="PARTITA_IVA"><label for="pec">{l s='PEC' mod='mpcustomerinvoice'}</label><input type="email" class="form-control" id="pec" name="pec"></div>
                                <div class="col-md-4 form-group mp-invoice-field" data-types="ENTE"><label for="cig">{l s='Codice CIG' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="cig" name="cig" maxlength="10"></div>
                                <div class="col-md-4 form-group mp-invoice-field" data-types="ENTE"><label for="cup">{l s='Codice CUP' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="cup" name="cup" maxlength="15"></div>
                            </div>
                        </section>
                    {elseif $is_invoice_address}
                        <section class="mp-invoice-section is-visible">
                            <h3 class="h5 mb-3">{l s='Dati di fatturazione' mod='mpcustomerinvoice'}</h3>
                            <div class="row">
                                <div class="col-md-4 form-group"><label for="invoice_type">{l s='Tipo intestatario' mod='mpcustomerinvoice'}</label><select class="form-control" id="invoice_type" disabled>
                                        <option value="PRIVATO" {if $invoice.type == 'PRIVATO'} selected{/if}>{l s='Privato' mod='mpcustomerinvoice'}</option>
                                        <option value="PARTITA_IVA" {if $invoice.type == 'PARTITA_IVA'} selected{/if}>{l s='Partita IVA' mod='mpcustomerinvoice'}</option>
                                        <option value="ENTE" {if $invoice.type == 'ENTE'} selected{/if}>{l s='Ente pubblico' mod='mpcustomerinvoice'}</option>
                                    </select></div>
                                <div class="col-md-8 form-group"><label for="company">{l s='Intestazione fattura' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="company" value="{$invoice.company|escape:'htmlall':'UTF-8'}" disabled></div>
                                <div class="col-md-4 form-group"><label for="vat_number">{l s='Partita IVA' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="vat_number" value="{$invoice.vat_number|escape:'htmlall':'UTF-8'}" disabled></div>
                                <div class="col-md-4 form-group"><label for="dni">{l s='Codice fiscale' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="dni" value="{$invoice.dni|escape:'htmlall':'UTF-8'}" disabled></div>
                                <div class="col-md-4 form-group"><label for="cuu">{l s='Codice CUU' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="cuu" value="{$invoice.cuu|escape:'htmlall':'UTF-8'}" disabled></div>
                                <div class="col-md-4 form-group"><label for="sdi">{l s='Codice SDI' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="sdi" value="{$invoice.sdi|escape:'htmlall':'UTF-8'}" disabled></div>
                                <div class="col-md-4 form-group"><label for="pec">{l s='PEC' mod='mpcustomerinvoice'}</label><input type="email" class="form-control" id="pec" value="{$invoice.pec|escape:'htmlall':'UTF-8'}" disabled></div>
                                <div class="col-md-4 form-group"><label for="cig">{l s='Codice CIG' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="cig" value="{$invoice.cig|escape:'htmlall':'UTF-8'}" disabled></div>
                                <div class="col-md-4 form-group"><label for="cup">{l s='Codice CUP' mod='mpcustomerinvoice'}</label><input type="text" class="form-control" id="cup" value="{$invoice.cup|escape:'htmlall':'UTF-8'}" disabled></div>
                            </div>
                        </section>
                        <div class="alert alert-info mt-4 mb-0">
                            {l s='Questo è il tuo indirizzo di fatturazione. I dati fiscali non sono modificabili da questa pagina.' mod='mpcustomerinvoice'}
                        </div>
                    {/if}
                    <div class="d-flex justify-content-end align-items-center mt-4">
                        <a href="{$back_url}" class="btn btn-outline-secondary mr-2"><i class="material-icons">&#xE5C4;</i> {l s='Indietro' mod='mpcustomerinvoice'}</a>
                        {if !$is_invoice_address}
                            <button type="submit" name="save" class="btn btn-primary"><i class="material-icons">&#xE161;</i> {l s='Salva indirizzo' mod='mpcustomerinvoice'}</button>
                        {/if}
                    </div>
            </div>
        </div>
    </form>
{/block}

{block name='javascript_bottom'}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('mp-address-form');
            const toggle = document.getElementById('want_invoice');
            const section = document.getElementById('mp-invoice-section');
            const type = document.getElementById('invoice_type');
            const feedback = document.getElementById('mp-form-feedback');
            const country = document.getElementById('id_country');
            const stateGroup = document.getElementById('state-group');
            const state = document.getElementById('id_state');
            const fields = document.querySelectorAll('.mp-invoice-field');

            if (toggle) {
                const updateInvoiceFields = () => {
                    const selectedType = type.value;
                    fields.forEach((field) => {
                        const visible = toggle.checked && field.dataset.types.split(',').includes(selectedType);
                        field.classList.toggle('is-visible', visible);
                        field.querySelector('input').disabled = !visible;
                    });
                };
                const updateInvoiceSection = () => {
                    section.classList.toggle('is-visible', toggle.checked);
                    toggle.closest('.mp-invoice-toggle').classList.toggle('is-active', toggle.checked);
                    type.disabled = !toggle.checked;
                    updateInvoiceFields();
                };
                toggle.addEventListener('change', updateInvoiceSection);
                type.addEventListener('change', updateInvoiceFields);
                updateInvoiceSection();
            }

            const refreshChosen = (element) => {
                if (window.jQuery && window.jQuery.fn.chosen) {
                    window.jQuery(element).trigger('chosen:updated');
                }
            };

            if (window.jQuery && window.jQuery.fn.chosen) {
                window.jQuery('.chosen-select').chosen({ disable_search_threshold: 8, search_contains: true, width: '100%' });
            }

            country.addEventListener('change', async () => {
                state.replaceChildren(new Option('{l s='Seleziona una provincia' mod='mpcustomerinvoice'}', ''));
                state.required = false;
                refreshChosen(state);
                if (!country.value) {
                    stateGroup.style.display = 'none';
                    return;
                }
                try {
                    const url = new URL('{$link->getModuleLink('mpcustomerinvoice', 'customer', ['ajax' => 1], true)}', window.location.origin);
                    url.searchParams.set('action', 'hasStates');
                    url.searchParams.set('countryId', country.value);
                    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) {
                        throw new Error('Unable to load states');
                    }
                    const data = await response.json();
                    if (!data.hasStates) {
                        stateGroup.style.display = 'none';
                        return;
                    }
                    data.options.forEach((item) => state.add(new Option(item.iso_code ? item.name + ' (' + item.iso_code + ')' : item.name, item.id_state)));
                    state.required = true;
                    stateGroup.style.display = '';
                    refreshChosen(state);
                } catch (_) {
                    stateGroup.style.display = 'none';
                }
            });

            if (toggle) {
                form.addEventListener('submit', async (event) => {
                    if (!toggle.checked) {
                        return;
                    }
                    event.preventDefault();
                    feedback.style.display = 'none';
                    const submit = form.querySelector('[name="save"]');
                    submit.disabled = true;
                    try {
                        const body = new URLSearchParams(new FormData(form));
                        body.set('ajax', '1');
                        body.set('action', 'validateInvoiceData');
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
                            body,
                        });
                        const data = await response.json();
                        if (!data.success) {
                            feedback.replaceChildren(...data.errors.map((error) => {
                                const message = document.createElement('div');
                                message.textContent = error;
                                return message;
                            }));
                            feedback.style.display = '';
                            return;
                        }
                        form.submit();
                    } catch (_) {
                        feedback.textContent = '{l s='Impossibile verificare i dati di fatturazione. Riprova.' mod='mpcustomerinvoice'}';
                        feedback.style.display = '';
                    } finally {
                        submit.disabled = false;
                    }
                });
            }
        });
    </script>
{/block}