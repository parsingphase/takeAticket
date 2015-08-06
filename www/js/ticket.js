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
        var block = '<div class="ticket well well-lg"><p class="text-center">' + title + '</p></div>';
        return block;
    },

    drawManagableTicket: function (ticket) {
        //console.log(['drawManagableTicket',ticket]);

        //var block = '<div class="ticket well well-lg"><p class="text-center">' + title + '</p></div>';
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
            out += Date.now().toLocaleString();

            $('#target').html(out);
        });
    },

    manage: function (tickets) {
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
        });
    },

    initTemplates: function () {
        this.manageTemplate = Handlebars.compile(
            '<div class="ticket well" data-ticket-id="{{ ticket.id }}">' +
            '        <div class="pull-right">' +
            '        <button class="btn btn-primary" data-ticket-id="{{ ticket.id }}">Performing</button>' +
            '        <button class="btn btn-danger" data-ticket-id="{{ ticket.id }}">Remove</button>' +
            '        </div>' +
            '        {{ ticket.offset }}: ' +
            '{{ ticket.title }}' +
            '</div>  '
        );
    },

    ticketOrderChanged: function () {
        console.log('Ticket order changed');
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

        console.log(['New order', idOrder]);

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
    }

};