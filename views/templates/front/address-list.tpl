{extends file='customer/page.tpl'}

{block name='page_title'}
    {l s='I tuoi indirizzi' mod='mpcustomerinvoice'}
{/block}

{block name='page_content'}
    <style>
        .mp-addresses {
            max-width: 1080px;
            margin: 0 auto;
        }

        .mp-addresses__hero {
            align-items: center;
            background: linear-gradient(135deg, #173b66, #2c72af);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(23, 59, 102, .18);
            color: #fff;
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding: 1.5rem 1.75rem;
        }

        .mp-addresses__hero h2 {
            color: #fff;
            margin: 0 0 .35rem;
        }

        .mp-addresses__hero p {
            margin: 0;
            opacity: .9;
        }

        .mp-addresses__add {
            background: #fff;
            border-radius: 8px;
            color: #1f609d;
            font-weight: 600;
            white-space: nowrap;
        }

        .mp-addresses__add:hover {
            background: #eaf4fc;
            color: #164a7b;
        }

        .mp-addresses__notice {
            border: 0;
            border-left: 4px solid #2c9b6d;
            border-radius: 10px;
            box-shadow: 0 3px 14px rgba(28, 69, 102, .08);
        }

        .mp-address-card {
            border: 1px solid #e3eaf1;
            border-radius: 14px;
            box-shadow: 0 5px 20px rgba(28, 69, 102, .08);
            height: calc(100% - 1rem);
            margin-bottom: 1rem;
            overflow: hidden;
            transition: box-shadow .2s ease, transform .2s ease;
        }

        .mp-address-card:hover {
            box-shadow: 0 10px 28px rgba(28, 69, 102, .15);
            transform: translateY(-2px);
        }

        .mp-address-card__header {
            align-items: flex-start;
            background: #f8fbfd;
            border-bottom: 1px solid #e9eef3;
            display: flex;
            justify-content: space-between;
            padding: 1rem 1.25rem;
        }

        .mp-address-card__title {
            color: #173b66;
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
        }

        .mp-address-card__type {
            background: #e9f3fc;
            border-radius: 999px;
            color: #2166a3;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .03em;
            padding: .3rem .65rem;
            text-transform: uppercase;
        }

        .mp-address-card__type--invoice {
            background: #e4f5ea;
            color: #277a50;
        }

        .mp-address-card__body {
            display: flex;
            flex-direction: column;
            min-height: 210px;
            padding: 1.25rem;
        }

        .mp-address-card__recipient {
            color: #172b3a;
            font-weight: 700;
            margin-bottom: .55rem;
        }

        .mp-address-card__details {
            color: #5c6c7a;
            line-height: 1.55;
            margin: 0;
        }

        .mp-address-card__phone {
            align-items: center;
            color: #456174;
            display: flex;
            font-size: .9rem;
            gap: .4rem;
            margin-top: .9rem;
        }

        .mp-address-card__actions {
            display: flex;
            gap: .65rem;
            margin-top: auto;
            padding-top: 1.25rem;
        }

        .mp-address-card__actions .btn {
            border-radius: 7px;
            flex: 1;
            font-weight: 600;
        }

        .mp-addresses__empty {
            background: #f8fbfd;
            border: 1px dashed #bacbd9;
            border-radius: 14px;
            padding: 3rem 1.5rem;
            text-align: center;
        }

        .mp-addresses__empty .material-icons {
            color: #2c72af;
            font-size: 2.5rem;
        }

        @media (max-width: 575px) {
            .mp-addresses__hero {
                align-items: flex-start;
                flex-direction: column;
                gap: 1rem;
            }

            .mp-addresses__add {
                width: 100%;
            }
        }
    </style>
    <section class="mp-addresses">
        <header class="mp-addresses__hero">
            <div>
                <h2 class="text-white">{l s='I tuoi indirizzi' mod='mpcustomerinvoice'}</h2>
                <p class="text-white">{l s='Gestisci i tuoi recapiti di spedizione e fatturazione.' mod='mpcustomerinvoice'}</p>
            </div>
            <a href="{$link->getModuleLink('mpcustomerinvoice', 'address', ['add' => 1])}" class="btn mp-addresses__add">
                <i class="material-icons">&#xE145;</i> {l s='Aggiungi indirizzo' mod='mpcustomerinvoice'}
            </a>
        </header>

        <div class="alert alert-info mp-addresses__notice" role="alert">
            {l s='Se il tuo indirizzo di spedizione è diverso dall’indirizzo di fatturazione, aggiungilo nella sezione indirizzi.' mod='mpcustomerinvoice'}
        </div>
        <div class="alert alert-warning mp-addresses__notice" role="alert">
            {l s='Puoi avere un solo indirizzo di spedizione. Nel caso di modifiche contatta l\'assistenza.' mod='mpcustomerinvoice'}
        </div>

        {if $addresses}
            <div class="row">
                {foreach from=$addresses item=address}
                    <div class="col-md-6 col-lg-4">
                        <article class="mp-address-card">
                            <header class="mp-address-card__header">
                                <h3 class="mp-address-card__title">{$address.alias|escape:'htmlall':'UTF-8'}</h3>
                                {if $address.id_address == $invoice_address_id}
                                    <span class="mp-address-card__type mp-address-card__type--invoice">{l s='Fatturazione' mod='mpcustomerinvoice'}</span>
                                {else}
                                    <span class="mp-address-card__type">{l s='Spedizione' mod='mpcustomerinvoice'}</span>
                                {/if}
                            </header>
                            <div class="mp-address-card__body">
                                <div class="mp-address-card__recipient">{$address.firstname|escape:'htmlall':'UTF-8'} {$address.lastname|escape:'htmlall':'UTF-8'}</div>
                                <p class="mp-address-card__details">
                                    {$address.address1|escape:'htmlall':'UTF-8'}<br>
                                    {if $address.address2}{$address.address2|escape:'htmlall':'UTF-8'}<br>{/if}
                                    {$address.postcode|escape:'htmlall':'UTF-8'} {$address.city|escape:'htmlall':'UTF-8'}<br>
                                    {$address.country|escape:'htmlall':'UTF-8'}
                                </p>
                                {if $address.phone || $address.phone_mobile}
                                    <div class="mp-address-card__phone"><i class="material-icons">&#xE0B0;</i>{if $address.phone}{$address.phone|escape:'htmlall':'UTF-8'}{/if}{if $address.phone && $address.phone_mobile} · {/if}{if $address.phone_mobile}{$address.phone_mobile|escape:'htmlall':'UTF-8'}{/if}</div>
                                {/if}
                                <div class="mp-address-card__actions">
                                    {if $address.id_address == $invoice_address_id}
                                        <a href="{$link->getModuleLink('mpcustomerinvoice', 'address', ['view' => 1, 'id_address' => $address.id_address])}" class="btn btn-primary"><i class="material-icons">&#xE8F4;</i> {l s='Vedi' mod='mpcustomerinvoice'}</a>
                                    {else}
                                        <a href="{$link->getModuleLink('mpcustomerinvoice', 'address', ['edit' => 1, 'id_address' => $address.id_address])}" class="btn btn-primary"><i class="material-icons">&#xE254;</i> {l s='Modifica' mod='mpcustomerinvoice'}</a>
                                        <a href="{$link->getModuleLink('mpcustomerinvoice', 'address', ['delete' => 1, 'id_address' => $address.id_address])}" class="btn btn-outline-danger" onclick="return confirm('{l s='Sei sicuro di voler eliminare questo indirizzo?' mod='mpcustomerinvoice'}')"><i class="material-icons">&#xE872;</i> {l s='Elimina' mod='mpcustomerinvoice'}</a>
                                    {/if}
                                </div>
                            </div>
                        </article>
                    </div>
                {/foreach}
            </div>
        {else}
            <div class="mp-addresses__empty">
                <i class="material-icons">&#xE88A;</i>
                <h3 class="h5 mt-3">{l s='Non hai ancora salvato nessun indirizzo.' mod='mpcustomerinvoice'}</h3>
                <p class="mb-0">{l s='Aggiungi il tuo primo indirizzo per iniziare.' mod='mpcustomerinvoice'}</p>
            </div>
        {/if}
    </section>
{/block}