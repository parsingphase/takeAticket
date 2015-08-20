/**
 * Created by wechsler on 04/08/15.
 */
var ticketer = {
    drawTemplate: null,
    manageTemplate: null,
    displayOptions: {},
    //
    //greet: function () {
    //    $('#target').text('Hi from ticketer');
    //},

    go: function () {
        this.initTemplates();

        ticketer.reloadTickets();
        setInterval(function () {
            ticketer.reloadTickets()
        }, 10000);
    },

    drawDisplayTicket: function (ticket) {
        //console.log(['drawDisplayTicket',ticket]);
        //console.log(ticket.title);
        //var title = ticket.title;
        //var block = '<div class="ticket well"><p class="text-center">' + title + '</p></div>';
        var block = this.drawTemplate({ticket: ticket});
        return block;
    },

    drawManagableTicket: function (ticket) {
        ticket.used = Number(ticket.used); // force int

        var block = this.manageTemplate({ticket: ticket});
        return block;

    },

    reloadTickets: function () {
        var that = this;
        console.log('reloadTickets');

        $.get('/api/next', function (tickets) {

            var out = '';
            for (var i = 0; i < tickets.length; i++) {
                var ticket = tickets[i];
                var block = that.drawDisplayTicket(ticket);
                out += block;
            }
            //out += Date.now().toLocaleString();

            $('#target').html(out);

            $('#target').find('.auto-font').each(
                function () {
                    var scale = 1.05 * this.scrollWidth / this.clientWidth; // extra scale to fit neatly
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
            var block = that.drawManagableTicket(ticket);
            out += block;
        }
        $('#target').html(out);


        $('.sortContainer').sortable({
            axis: 'y',
            update: function (event, ui) {
                that.ticketOrderChanged()
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

        this.enableButtons($('.sortContainer'));
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

        this.drawTemplate = Handlebars.compile(
            '<div class="ticket well" data-ticket-id="{{ ticket.id }}">' +
            '        <div class="ticket-inner">' +
            '        <p class="text-center band auto-font">{{ticket.title}}</p>' +
            (this.displayOptions.songInPreview ? '{{#if ticket.song}}<p class="text-center song auto-font">{{ticket.song.artist}}: {{ticket.song.title}}</p>{{/if}}' : '') +
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
                    titleInput.val('');
                    songInput.val('');
                    $('#target').append(that.drawManagableTicket(data.ticket));
                    var ticketId = data.ticket.id;
                    var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
                    that.enableButtons(ticketBlock);
                },
                error: function (xhr, status, error) {
                    console.log('addTicketTitle post ERROR: ' + status);
                }
            }
        );
    },

    performButtonCallback: function (button) {
        var that = this;

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
                    var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
                    ticketBlock.addClass('used');
                    ticketBlock.append(' (done)');
                },
                error: function (xhr, status, error) {
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
                    var ticketBlock = $('.ticket[data-ticket-id="' + ticketId + '"]');
                    ticketBlock.remove();
                },
                error: function (xhr, status, error) {
                    console.log('removeButtonCallback post ERROR: ' + status);
                }
            }
        );
    }

};