/**
 * Created by wechsler on 04/08/15.
 */
var ticketer = {
    drawTemplate: null,
    manageTemplate: null,
    songAutocompleteItemTemplate: null,
    displayOptions: {},

    go: function () {
        this.initTemplates();

        ticketer.reloadTickets();
        setInterval(function () {
            ticketer.reloadTickets();
        }, 10000);
    },

    drawDisplayTicket: function (ticket) {
        return this.drawTemplate({ticket: ticket});
    },

    drawManageableTicket: function (ticket) {
        ticket.used = Number(ticket.used); // force int

        return this.manageTemplate({ticket: ticket});
    },

    reloadTickets: function () {
        var that = this;
        console.log('reloadTickets');

        $.get('/api/next', function (tickets) {

            var out = '';
            for (var i = 0; i < tickets.length; i++) {
                var ticket = tickets[i];
                out += that.drawDisplayTicket(ticket);
            }
            //out += Date.now().toLocaleString();

            var target = $('#target');
            target.html(out);

            target.find('.auto-font').each(
                function () {
                    var scale = 1.05 * Math.min(this.scrollWidth / this.clientWidth); // extra scale to fit neatly
                    var font = Number($(this).css('font-size').replace(/[^0-9]+$/, ''));
                    //console.log(['width', outerWidth, outerScroll, innerWidth, innerScroll, font]);
                    $(this).css('font-size', (font / scale) + 'px');
                }
            );

        });
    },

    enableButtons: function (topElement) {
        var that = this;

        $(topElement).find('.performButton').click(function () {
            that.performButtonCallback(this);
        });

        $(topElement).find('.removeButton').click(function () {
            that.removeButtonCallback(this);
        });
    },

    manage: function (tickets) {
        var that = this;
        this.initTemplates();
        //console.log(tickets);

        var out = '';
        for (var i = 0; i < tickets.length; i++) {
            var ticket = tickets[i];
            out += that.drawManageableTicket(ticket);
        }
        $('#target').html(out);

        var $sortContainer = $('.sortContainer');
        $sortContainer.sortable({
            axis: 'y',
            update: function (event, ui) {
                void(event);
                void(ui);
                that.ticketOrderChanged();
            }
        }).disableSelection().css('cursor', 'move');

        $('.addTicketButton').click(function () {
            that.addTicketCallback();
        });

        $('.addTicketTitle').keydown(function (e) {
            if (e.keyCode == 13) {
                that.addTicketCallback();
            }
        });

        $('.addSongTitle').keyup(
            function () {
                var input = $(this);
                var searchString = input.val();
                //console.log('SS: ' + searchString);
                if (searchString.length > 3) {
                    console.log('SS+: ' + searchString);
                    $.ajax({
                        method: 'POST',
                        data: {
                            searchString: searchString
                        },
                        url: '/api/songSearch',
                        success: function (data, status) {
                            console.log(['songSearch returned', data]);
                            var songs = data.songs;
                            if (input.val() == data.searchString) {
                                // ensure autocomplete response is still valid for current input value
                                var out = '';
                                for (var i = 0; i < songs.length; i++) {
                                    var song = songs[i];
                                    out += that.songAutocompleteItemTemplate({song: song});
                                }
                                $('.songComplete').html(out);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.log('songSearch post ERROR: ' + status);
                            void(error);
                        }
                    });
                } else {
                    $('.songComplete').html('');
                }
            }
        );

        this.enableButtons($sortContainer);
    },

    initTemplates: function () {
        this.manageTemplate = Handlebars.compile(
            '<div class="ticket well {{#if ticket.used}}used{{/if}}" data-ticket-id="{{ ticket.id }}">' +
            '        <div class="pull-right">' +
            '        <button class="btn btn-primary performButton" data-ticket-id="{{ ticket.id }}">Performing</button>' +
            '        <button class="btn btn-danger removeButton" data-ticket-id="{{ ticket.id }}">Remove</button>' +
            '        </div>' +
            '        <b>#{{ ticket.id }}:</b> ' +
            'Band: {{ ticket.title }}' +
            '{{#if ticket.used}} (done){{/if}}' +
            '{{#if ticket.song}}<br />{{ticket.song.artist}}: {{ticket.song.title}}{{/if}}' +
            '</div>  '
        );

        //var block = '<div class="ticket well"><p class="text-center">' + title + '</p></div>';

        //noinspection JSUnresolvedVariable
        this.drawTemplate = Handlebars.compile(
            '<div class="ticket well" data-ticket-id="{{ ticket.id }}">' +
            '        <div class="ticket-inner">' +
            '        <p class="text-center band auto-font">{{ticket.title}}</p>' +
            (this.displayOptions.songInPreview ? '{{#if ticket.song}}<p class="text-center song auto-font">{{ticket.song.artist}}: {{ticket.song.title}}</p>{{/if}}' : '') +
            '        </div>' +
            '</div>  '
        );

        this.songAutocompleteItemTemplate = Handlebars.compile(
            '<div class="acSong" data-song-id="{{ song.id }}">' +
            '        <div class="acSong-inner">' +
            '        <p class="">{{song.artist}}: {{song.title}}</p>' +
            '        </div>' +
            '</div>  '
        );
    },

    ticketOrderChanged: function () {
        //console.log('Ticket order changed');
        var idOrder = [];
        $('#target').find('.ticket').each(
            function () {
                var ticketBlock = $(this);
                //console.log(ticketBlock.data());
                var ticketId = ticketBlock.data('ticketId');
                //console.log('Id: ' + ticketId);
                idOrder.push(ticketId);
            }
        );

        //console.log(['New order', idOrder]);

        $.ajax({
            method: 'POST',
            data: {
                idOrder: idOrder
            },
            url: '/api/newOrder',
            success: function (data, status) {
                console.log('ticketOrderChanged post OK: ' + status);
            },
            error: function (xhr, status, error) {
                console.log('ticketOrderChanged post ERROR: ' + status);
                void(error);
            }
        });
    },

    addTicketCallback: function () {
        var that = this;
        var titleInput = $('.addTicketTitle');
        var newTitle = titleInput.val();
        var songInput = $('.addSongTitle');
        var songTitle = songInput.val();
        console.log('addTicket: ' + newTitle);
        $.ajax({
                method: 'POST',
                data: {
                    title: newTitle,
                    song: songTitle
                },
                url: '/api/newTicket',
                success: function (data, status) {
                    void(status);
                    titleInput.val('');
                    songInput.val('');
                    $('#target').append(that.drawManageableTicket(data.ticket));
                    var ticketId = data.ticket.id;
                    var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
                    that.enableButtons(ticketBlock);
                },
                error: function (xhr, status, error) {
                    void(error);
                    console.log('addTicketTitle post ERROR: ' + status);
                }
            }
        );
    },

    performButtonCallback: function (button) {
        //var that = this;

        button = $(button);
        var ticketId = button.data('ticketId');
        console.log('performButtonCallback: ' + ticketId);
        $.ajax({
                method: 'POST',
                data: {
                    ticketId: ticketId
                },
                url: '/api/useTicket',
                success: function (data, status) {
                    void(data);
                    void(status);
                    var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
                    ticketBlock.addClass('used');
                    ticketBlock.append(' (done)');
                },
                error: function (xhr, status, error) {
                    void(xhr);
                    void(error);
                    console.log('performButtonCallback post ERROR: ' + status);
                }
            }
        );
    },

    removeButtonCallback: function (button) {
        button = $(button);
        var ticketId = button.data('ticketId');
        console.log('removeButtonCallback: ' + ticketId);
        $.ajax({
                method: 'POST',
                data: {
                    ticketId: ticketId
                },
                url: '/api/deleteTicket',
                success: function (data, status) {
                    void(status);
                    var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
                    ticketBlock.remove();
                },
                error: function (xhr, status, error) {
                    void(error);
                    console.log('removeButtonCallback post ERROR: ' + status);
                }
            }
        );
    }

};