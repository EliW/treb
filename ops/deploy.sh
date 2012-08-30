#!/bin/bash

##############################################################################
#
# Treb Deploy script
#
# This script will push out a Treb site from a SVN repo to all appropriate servers.
#  Make sure to edit the server-lists in the arrays to match existing servers.
#
# This was a one-off program that was used for a specific application, and has been made
#  a little more configurable at this point.  There are decent chances that you may need
#  to actually hack this to use it for your our situation; however, it's very straight
#  forward to understand and make said changes.
#
# To use, make sure that you have 'svn', 'scp', and 'ssh' all accessible to 
#  you as well as /tmp usable as a temp directory.  Then just run the script,
#  per the $USAGE string below, specifying a target (test, live) as well as 
#  either a branch, a tag, or the trunk as what is being pushed.  It should 
#  do the rest for you.
#
# There are a number of assumptions that this deployment script makes, even given the
#   configurability that it does have, If you want to change these assumptions, you will need
#   to edit the script:
#  * That standard unix utils (svn, scp, ssh, etc) will be available in the path
#  * That you sure your codebase in SVN, protected by username/password
#  * That you will deploy to remote servers, protected by a username and an identity key
#    (aka, private key).  This is, for example, how Amazon AWS servers are default protected
#  * That you will store this key in your SVN, and provide the path to it.
#  * That you want a set of standard practices, compressing JS/CSS, unless already minified,
#    to send emails to an address on any TAG push (assuming that a tag means something final),
#    that you want to make announcements via an IRC bot, etc.
#  * That you will name configuration files in /config/ of treb, the same as your target types.  
#    So if you have a target of 'live', then you will have a live.config.xml file.
#  * That this deploys to a directory on the servers of your choosing, but automatically creates
#    a subdirectory with the name of the 'target', and then makes a unique directory for the deploy
#    and at the end, aliases /current to the latest.  Your apache webroot should be configured to
#    look for this 'current' directory.
#
##############################################################################

# Define your valid deployment targets, such as 'dev', 'test', 'live', etc.  And for each of them
#  make a list of servers that will be deployed to when you push to that target.  Each of these
#  lists is space-separated and machines to push to.  IP or DNS is fine.
TARGETS="test preview live"
SERVERS_test="10.0.0.1"
SERVERS_preview="10.0.1.2 10.0.1.3"
SERVERS_live="live1.example.com live2.example.com"

# The following are configuration options that will change less frequently, but you should set up
#  correctly for your system & deployment situation:
TMP="/tmp"                             # Local temp directory to store things in.
SVNUSER="svn username"                 # Username to your SVN codebase
SVNPASS="svn password"                 # Password to your SVN codebase
SVNREPO="https://example.com/svn/repo" # Full URL to your SVN Repository
DIRECTORY="/on/my/host/deployments"    # Remote directory that all deployments will be saved in
SSHKEY="/ops/example.pem"              # Identitiy/private key for remote access
SSHUSER="root"                         # Username, associated with identity file
EMAIL="deploylist@example.com"         # Email to sent reports of TAG pushes to.

# Weird Stuff - Don't mess with this unless you understand it
USAGE="Usage: $0 <target> [--trunk] [-b <branch>] [-t <tag>] [--nocompress] [--servers <ip>] [--transport (scp|tar)]"
SVN="svn --non-interactive --no-auth-cache --trust-server-cert --username $SVNUSER --password $SVNPASS"
DUMMYKNOWN="$TMP/deploy.known"
SVNOPTS="-o UserKnownHostsFile=$DUMMYKNOWN -o StrictHostKeyChecking=no" # Stops security checks

#####################
# Parameter Parsing #
#####################

# Set some default parameters - EDIT THIS if you want to change the default behaviour
transport="tar" # Default the transport to TAR
compress=1      # Default compression to ON
irc=1           # Default irc notification to ON
email=1         # Default email notification to ON
verbose=0       # Default verboseness to OFF

# Our own variables
path=
name=
type=
servers=
target=$1
shift

# Ensure we got a valid target
for valid in $TARGETS
do
    if [ "$target" == "$valid" ]
    then
        svar="SERVERS_${valid}"
        servers=${!svar}
        break
    fi
done

# So did we get one?
if [ -z "$servers" ]
then
    echo "Unrecognized target: $target"
    echo $USAGE
    exit 1
fi

# Prepare the destination now, adding in the target:
DESTINATION="$DIRECTORY/$target"

# Handle the additional command line parameters:
while [ $# -gt 0 ]
do
    case "$1" in
        --trunk) # Stating that the trunk is to be pushed
            path="/trunk"; name="trunk"; type="trunk";;
        -b | --branch) # Choosing a branch to push
            path="/branches/$2"; name="branch-$2"; type="branch"; shift;;
        -t | --tag) # Choosing a tag to push
            path="/tags/$2"; name="tag-$2"; type="tag"; shift;;
        --servers) # Overriding the servers arrays - Use to push to specific servers
            servers=$2; shift;;
        --transport) # Override the transport method - In case it matters:
            transport=$2; shift;;
        --nocompress) # Override the normal JS/CSS compression
            compress=0;;
        --noirc) # Override the normal irc announcements
            irc=0;;
        --noemail) # Override the normal email announcements
            email=0;;
        --verbose) # Override the normal email announcements
            verbose=1;;
        *) echo "Unrecognized parameter: $1"; echo $USAGE; exit 1;;
    esac
    shift
done

# Did we get a path?
if [ -z "$path" ]
then
    echo "An SVN path must be specified, either trunk/branch/tag"
    echo $USAGE
    exit 1
fi

#####################
# Actual processing #
#####################

# Export from SVN:
stamp=`date +%Y.%m.%d.%H.%M.%S`
unix=`date +%s`
echo "==> Beginning SVN export of $path …"
version=`$SVN export "$SVNREPO$path" "$TMP/$name-$stamp" | tail -1 | cut -d' ' -f3 | cut -d'.' -f1`

# Handle post-processing here
echo "==> Postprocessing …"

# Move the key for later use, plus we don't want to push it to server:
echo " Preparing permissions …"
mv -f "$TMP/$name-$stamp$SSHKEY" "$TMP/pushkey.pem"
chmod 600 "$TMP/pushkey.pem"

# Add in JS/CSS Compression
if [ "$compress" -eq 1 ]
then
    echo " Compressing all JS files …"
    find $TMP/$name-$stamp/web/js -name '*.js' | grep -v '\.min\.js$' | while read file
    do
        if [ -n "$file" ] # Watch out for empty directories
        then
            if [ "$verbose" -eq 1 ]
            then
                echo " * Compressing $file …";
            fi
            java -jar "$TMP/$name-$stamp/ops/bin/yuicompressor.jar" "$file" -o "$file"
        fi
    done
    
    echo " Compressing all CSS files …"
    find $TMP/$name-$stamp/web/css -name '*.css' | grep -v '\.min\.css$' | while read file
    do
        if [ -n "$file" ] # Watch out for empty directories
        then
            if [ "$verbose" -eq 1 ]
            then
                echo " * Compressing $file …";
            fi
            java -jar "$TMP/$name-$stamp/ops/bin/yuicompressor.jar" "$file" -o "$file"
        fi
    done
else
    echo " Skipping JS/CSS Compression …"
fi

# Either way, now delete the yuicompressor, we don't need to push that to the server:
rm -f "$TMP/$name-$stamp/ops/bin/yuicompressor.jar"

# Copy appropriate config.xml file - while doing search/replace:
echo " Coping $target config file …"
config="$TMP/$name-$stamp/config"
cat "$config/$target.config.xml" | sed "s/%NAME%/$name/g" | sed "s/%UNIX%/$unix/g" | sed "s/%VERSION%/$version/g" > "$config/config.xml"

# Time to actually push it
echo "==> Pushing …"

# Don't assume that destination path exists. Force create it - Allows initial deploy
#  NOTE: If this is a problem in the future, we could make a flag to do this.
# UPDATE: This now serves another feature as well.  I moved away from using the '/dev/null'
#  version of getting around security checking to a real, but new, file.  This loop uses
#  the -q flag to supress the 'warning' about adding a new host, so all the rest of
#  the commands can still have good output without having to supress warnings.
for host in $servers
do
    echo " Ensuring directories on $host …"
    ssh $SSHOPTS -q -i "$TMP/pushkey.pem" "$SSHUSER@$host" "mkdir -p $DESTINATION"
done

#############
# Transport #
#############

case "$transport" in
    tar)
        # Almost the same as above (ok, not really).  Tarballs the whole thing up, then scp's it:
        echo " Using tar for transport, creating tarball …"
        tar -cf "$TMP/$name-$stamp.tar" -C "$TMP" "$name-$stamp"
        for host in $servers
        do
            echo " Copying tarball to $host …"
            scp $SSHOPTS -C -r -i "$TMP/pushkey.pem" "$TMP/$name-$stamp.tar" "$SSHUSER@$host:$DESTINATION/"
            echo " Uncompressing tarball …"
            ssh $SSHOPTS -i "$TMP/pushkey.pem" "$SSHUSER@$host" "cd $DESTINATION; tar -xf $name-$stamp.tar; rm $name-$stamp.tar"
        done
        rm "$TMP/$name-$stamp.tar"
        ;;
    *)
        # Default to using a basic SCP command to transfer data.  Can be slow and noisy if large:
        echo " Using SCP for transport …"
        for host in $servers
        do
            echo " Copying to $host …"
            scp $SSHOPTS -C -r -i "$TMP/pushkey.pem" "$TMP/$name-$stamp" "$SSHUSER@$host:$DESTINATION/$name-$stamp"
        done
        ;;
esac

################
# Finalization #
################

# Change over the symlinks:
for host in $servers
do
    echo " Changing symlink on $host …"
    ssh $SSHOPTS -i "$TMP/pushkey.pem" "$SSHUSER@$host" "cd $DESTINATION; rm current; ln -s $name-$stamp current"
done

echo "==> Making any requested announcements …"

# Now unless they said not to.  Tell IRC about this:
if [ "$irc" -eq 1 ]
then
    # Messy, but so this can run in the background, copy ircsay to TMP and leave it:
    cp "$TMP/$name-$stamp/ops/ircsay.php" "$TMP/ircsay.php"
    php "$TMP/ircsay.php" "[DEPLOYMENT] `whoami` pushed $name to $target" &
fi

# And then, email the company - But only if TAG
if [ -n "$EMAIL" ] && [ "$email" -eq 1 ] && [ "$type" == "tag" ]
then
    $SVNCOMMAND log "$SVNREPO$path" --stop-on-copy | mail -s "[DEPLOYMENT] `whoami` pushed $name to $target" $EMAIL &
fi

# Do any final cleanup.
echo "==> Cleaning up after ourselves …"
rm -rf "$TMP/$name-$stamp"
rm -f "$TMP/pushkey.pem"
rm -f "$DUMMYKNOWN"

# Woot!
echo "==> DONE"
printf '\a\a\a'