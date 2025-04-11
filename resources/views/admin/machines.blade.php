@extends('admin::index')
@section('content')
    <style>
        .custom-pagination {
            text-align: center;
            margin-top: 20px;
        }

        .custom-pagination .pagination {
            display: inline-block;
        }

        .custom-pagination .pagination li {
            display: inline;
            margin: 0 5px;
        }

        .custom-pagination .pagination li a {
            color: #007bff;
            text-decoration: none;
        }

        .custom-pagination .pagination li a:hover {
            text-decoration: underline;
        }
    </style>
    <section class="content-header">
        <h1>Dashboard</h1>
    </section>
    <div class="row" style="padding-left: 2rem; padding-right: 2rem; padding-top: 3rem;">
        @foreach($machines as $machine)
            <div class="col-md-4">
                <div class="box box-widget widget-user">
                    <div class="widget-user-header bg-aqua-active">
                        <h5 class="widget-user-username">{{ $machine->name }}</h5>
                        <h5 class="widget-user-desc">ID: {{ $machine->id }}</h5>
                        <h5 class="widget-user-desc">Location: {{ $machine->location->name }}</h5>
                        <h5 class="widget-user-desc" style="display: flex; justify-content: space-between;">
                            <div id="status-{{ $machine->token }}"><button class="btn btn-danger btn-sm">OFFLINE</button></div>
                            <div id="program-{{ $machine->token }}"><button class="btn btn-dropbox btn-sm">-</button></div>
                        </h5>
                    </div>
                    <div class="box-footer">
                        <div class="row">
                            @foreach($machine->machinePlans as $plan)
                                <div class="col-sm-12" style="padding-bottom: 1rem">
                                    <button class="btn btn-primary btn-block">{{ $plan->name }}</button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="pagination-wrapper custom-pagination">
        {{ $machines->links() }}
    </div>

    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <script>
        const machinePlans = @json($machines->mapWithKeys(function ($machine) {
        return [$machine->token => $machine->machinePlans->pluck('name', 'program_code')];
    }));
    </script>
    <script>
        const mqttServer = "re.saveapp.cc";
        const mqttUser = "user1";
        const mqttPassword = "12345678";
        const mqttPort = 9001; // Updated WebSocket port

        const client = mqtt.connect(`wss://${mqttServer}:${mqttPort}`, {
            username: mqttUser,
            password: mqttPassword
        });

        client.on('connect', function () {
            console.log('Connected to MQTT broker');
            client.subscribe('#', function (err) {
                if (!err) {
                    console.log('Subscribed to all topics');
                } else {
                    console.error('Subscription error:', err);
                }
            });
        });

        client.on('message', function (topic, message) {
            const realtimeRegex = /^(.+)\/realtime$/;
            const runingRegex = /^(.+)\/running$/;
            const realtimeMatch = topic.match(realtimeRegex);
            const runingMatch = topic.match(runingRegex);

            if (realtimeMatch) {
                const key = realtimeMatch[1];
                const data = JSON.parse(message.toString());
                console.log(`Key: ${key}`);
                console.log(`Data:`, data);

                const lastActiveTime = data.last_active_time;
                const currentTime = Math.floor(Date.now() / 1000); // Current time in seconds
                const statusElement = document.getElementById(`status-${key}`);

                if (statusElement) {
                    if (currentTime - lastActiveTime <= 30) {
                        statusElement.innerHTML = '<button class="btn btn-success btn-sm">ONLINE</button>';
                    }
                }
            } else if (runingMatch) {
                const key = runingMatch[1];
                const data = JSON.parse(message.toString());
                console.log(`Key: ${key}`);
                console.log(`Data:`, data);

                const programCode = data.program_code;
                console.log(data.program_code);
                const programElement = document.getElementById(`program-${key}`);

                if (programElement) {
                    if (programCode === 0) {
                        programElement.innerHTML = '<button class="btn btn-dropbox btn-sm">-</button>';
                    } else {
                        const programName = machinePlans[key][programCode] || programCode;
                        programElement.innerHTML = `<button class="btn btn-dropbox btn-sm">${programName}</button>`;
                    }
                }
            }
        });
    </script>
@endsection
