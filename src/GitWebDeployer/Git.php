<?php

namespace GitWebDeployer;


class Git {

    private $config;
    private $request;
    private $output;

    public function __construct($config){
        $this->config = $config;
    }

    public function test(){
        echo "test";
    }

    public function deploy(){
        $payload = file_get_contents('php://input');
        if (empty($payload) || "sha1=" . hash_hmac('sha1', $payload, $this->secret_) != $_SERVER['HTTP_X_HUB_SIGNATURE'])
            $this->serviceExit(500);

        $payload = json_decode($payload, true);
        $hostname = (empty($hostname)) ? gethostbyaddr($_SERVER['REMOTE_ADDR']) : $hostname;

        $commit = $payload['after'];
        $ref = explode('/', $payload['ref']);
        $branch = array_pop($ref);
        $dir = $this->config[strpos($branch, 'dev') !== false ? 'dev' : 'master'];

        $commands = array(
            'whoami',
            'cd ' . $dir . '; echo $PWD',
            'cd ' . $dir . ' && git pull',
            'cd ' . $dir . ' && git checkout ' . $commit,
            'cd ' . $dir . ' && git status',
            array('cd ' . $dir . ' && git log -1 --pretty=format:"%h - %s (%ci)" --abbrev-commit', $dir . '/data/currentCommit.html'),
            'cd ' . $dir . '/api && git diff-tree --no-commit-id --name-only -r ' . $commit . ' | egrep "composer.json" && /etc/composer.phar install',
            'cd ' . $dir . ' && git diff-tree --no-commit-id --name-only -r ' . $commit . ' | egrep "(.*).sql" | sort',
            'cd ' . $dir . ' && mysql -D letsbro -u root -pAiveukeiqu5Zooru < `git diff-tree --no-commit-id --name-only -r ' . $commit . ' | egrep "(.*).sql" | sort`',
            'cd ' . $dir . ' && HOME=.bower bower install'
        );

        // Run the commands for output
        $this->output = '';
        foreach($commands AS $command){
            // Run it
            $options = array();
            if (is_array($command))
            {
                $options[] = $command[1];
                $command = $command[0];
            }
            $command .= ' 2>&1';
            $tmp = shell_exec($command);
            // Output
            if (!empty($options))
                file_put_contents($options[0], $tmp);
            $this->output .= "<span class='green'>\$</span> <span class='blue'>{$command}\n</span>";
            $this->output .= (empty($tmp)) ? "No result.\n" : htmlentities(trim($tmp)) . "\n";
        }

        $this->request = array(
            'hostname' => $hostname,
            'ref' => $ref,
            'commit' => $commit,
            'branch' => $branch,
            'dir' => $dir
        );

        $this->output();
    }

    private function output(){
        // Make it pretty for manual user access (and why not?)

        function hideSensitiveData($buffer){
            return str_replace(
                array('-D letsbro -u root -pAiveukeiqu5Zooru'),
                array('-D Dabaze -u Dauzer -pDapassword'),
                $buffer
            );
        }

        ob_start($this->config['hideSensitiveData']);
        echo <<< EOF
        <!DOCTYPE HTML>
        <html lang="en-US">
        <head>
            <meta charset="UTF-8">
            <title>Let's bro corporation -- deploy</title>
            <style>
                .green { color: #6BE234; }
                .blue { color: #729FCF; }
                .red { color: #FF0000; }
            </style>
        </head>
        <body style="background-color: #000000; color: #FFFFFF; font-weight: bold; padding: 0 10px; max-width:100%; word-wrap: break-word;">
            <pre>
             .  ____  .    ____________________________
             |/      \|   |                            |
            [| <span class="red">&hearts;    &hearts;</span> |]  | Deployment Request Headers |
             |___==___|  /                      &copy; 2014 |
                          |____________________________|

            <span class="green">Deployment requested by :</span> <span class="blue">{$this->request['hostname']}</span><br/>

            <span class="green">Commit:</span> <span class="blue">{$this->request['commit']}</span><br />
            <span class="green">Branch:</span> <span class="blue">{$this->request['branch']}</span><br />
            <span class="green">Directory:</span> <span class="blue">{$this->request['dir']}</span><br />

            <span class="green">&#36;_SERVER</span>
EOF;
        print_r($_SERVER);
        echo <<< EOF
            <span class="green">Payload</span>
EOF;
        print_r($this->request['payload']);
        echo <<< EOF
                .  ____  .    ____________________________
             |/      \|   |                            |
            [| <span class="red">&hearts;    &hearts;</span> |]  |       Lets bro corporation |
             |___==___|  /                      &copy; 2014 |
                          |____________________________|

                {$this->request['output']}
            </pre>
        </body>
        </html>
EOF;

        $deployContent = $this->config['hideSensitiveData'](ob_get_contents());
        file_put_contents($this->request['dir'] . $this->request[$this->request['branch']]['outputFile'], $deployContent);
        ob_end_flush();
    }

    private function serviceExit($code, $message = null){
        http_response_code($code);
        exit($message);
    }

} 