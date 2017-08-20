""" Routines to manage using Docker for Simple File Gallery development """
import argparse
import os
import subprocess
import webbrowser

def build_dev_container():
    """ Build the Docker container used for development """
    subprocess.call("docker build --tag gallery:development " \
                    "--build-arg INSTALL_PHPUNIT=1 --build-arg INSTALL_XDEBUG=1 " \
                    "--build-arg INSTALL_NODE=1 --file Dockerfile.gallery .")

def build_deploy_container():
    """ Build the Docker container to deploy """
    subprocess.call("docker build --tag gallery:development " \
                    "--build-arg INSTALL_PHPUNIT=0 --build-arg INSTALL_XDEBUG=0 " \
                    "--build-arg INSTALL_NODE=0 --file Dockerfile.gallery .")

def start_dev_container():
    """ Start the Docker container used for development """
    subprocess.call("docker run -d --rm -p 8080:80 -v " + os.getcwd() +
                    ":/var/www/html -v " + os.getcwd() + "/logs/:/var/log gallery:development")

def stop_dev_container():
    """ Stop the Docker container used for development """
    cmd = subprocess.Popen("docker ps --filter ancestor=gallery:development "\
                           "--format \"{{.ID}}\"", shell=True, stdout=subprocess.PIPE)
    for line in cmd.stdout:
        if line:
            subprocess.call("docker kill " + line.strip())

def run_shell_dev_container():
    """ Bring up a shell for the Docker container used for development """
    cmd = subprocess.Popen('docker ps --filter ancestor=gallery:development --format "{{.ID}}"',
                           shell=True, stdout=subprocess.PIPE)
    for line in cmd.stdout:
        if line:
            subprocess.call("docker exec -it " + line.strip() + " bash")

def run_webservice_test():
    """ Run webservice unit tests """
    subprocess.call("docker run --rm -v " + os.getcwd() + ":/var/www/html -v " +
                    os.getcwd() + "/logs/:/var/log gallery:development " \
                    "bash -c \"cd gallery/webservice/test && phpunit\"")
    webbrowser.open_new_tab(os.path.realpath(
        "./gallery/webservice/test/results/coverage.html/index.html"))
    webbrowser.open_new_tab(os.path.realpath(
        "./gallery/webservice/test/results/testdox.html"))

def install_basic_viewer():
    """ Install libraries required for basic viewer """
    subprocess.call("docker-compose run development bash -c " \
        "\"cd gallery/viewers/basic " \
        "&& npm install" \
        "&& cp node_modules/jquery/dist/{jquery.min.js,jquery.min.map,jquery.js} js " \
        "&& cp node_modules/mustache/{mustache.js,mustache.min.js} js " \
        "&& cp node_modules/font-awesome/css/* css " \
        "&& mkdir -p fonts"
        "&& cp node_modules/font-awesome/fonts/* fonts " \
        "&& rm -r -f node_modules " \
        "\"")

def launch_parser():
    """ Launch the argument parser """
    parser = argparse.ArgumentParser(description="Utilities for working on Instant File Gallery")
    parser.add_argument("command", type=str, help="Command to execute", choices=['build', 'start', 'stop', 'shell', 'webservice-test', 'install-basic-viewer', 'build-deploy'])
    args = parser.parse_args()
    if args.command == 'build':
        build_dev_container()
    elif args.command == 'start':
        start_dev_container()
    elif args.command == 'stop':
        stop_dev_container()
    elif args.command == 'shell':
        run_shell_dev_container()
    elif args.command == 'webservice-test':
        run_webservice_test()
    elif args.command == 'install-basic-viewer':
        install_basic_viewer()

launch_parser()
