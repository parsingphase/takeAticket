/**
 * Created by wechsler on 04/08/15.
 */
var ticketer = {
    drawTemplate: null,
    manageTemplate: null,

    greet: function () {
        $('#target').text('Hi from ticketer');
    },

    go: function () {
        ticketer.reloadTickets();
        setInterval(function () {
            ticketer.reloadTickets()
        }, 10000);
    },

    drawDisplayTicket: function (ticket) {
        //console.log(['drawDisplayTicket',ticket]);
        //console.log(ticket.title);
        var title = ticket.title;
        var block = '<div class="ticket well"><p class="text-center">' + title + '</p></div>';
        return block;
    },

    drawManagableTicket: function (ticket) {
        ticket.used = 0 + Number(ticket.used); // force int

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
        });
    },

    manage: function (tickets) {
        var that = this;
        this.initTemplates();
        //console.log(tickets);
        var that = this;

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

        $('.performButton').click(function () {
            that.performButtonCallback(this);
        });
        $('.removeButton').click(function () {
            that.removeButtonCallback(this);
        });
    },

    initTemplates: function () {
        this.manageTemplate = Handlebars.compile(
            '<div class="ticket well {{#if ticket.used}}used{{/if}}" data-ticket-id="{{ ticket.id }}">' +
            '        <div class="pull-right">' +
            '        <button class="btn btn-primary performButton" data-ticket-id="{{ ticket.id }}">Performing</button>' +
            '        <button class="btn btn-danger removeButton" data-ticket-id="{{ ticket.id }}">Remove</button>' +
            '        </div>' +
            '        #{{ ticket.id }}: ' +
            'Band {{ ticket.title }}' +
            '{{#if ticket.used}} (done){{/if}}' +
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
        console.log('addTicket: ' + newTitle);
        $.ajax({
                method: 'POST',
                data: {
                    title: newTitle
                },
                url: '/api/newTicket',
                success: function (data, status) {
                    titleInput.val('');
                    $('#target').append(that.drawManagableTicket(data.ticket));
                },
                error: function (xhr, status, error) {
                    console.log('addTicketTitle post ERROR: ' + status);
                }
            }
        );
    },

    performButtonCallback: function (button) {
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