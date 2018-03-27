#!/bin/bash
SOURCEFILE=$1
FILENAME=$(basename "${SOURCEFILE}")
DIR=$(dirname "${SOURCEFILE}")
SITE="${DIR##*/}"
EXT="${FILENAME##*.}"
DESTDIR="/home/skippy/${SITE}"
WEBROOT="/var/www/${SITE}"
LOGFILE="/home/skippy/micropub.log"

if [ -z "${EXT}" ]; then
  # no extension?  no bueno.
  exit 1;
fi

case $EXT in
  jpeg|jpg|gif|png)
    echo "Copying ${SOURCEFILE} to ${DESTDIR}" >> $LOGFILE;
    cp "${SOURCEFILE}" "${DESTDIR}/static/images/";
    chmod 0644 "${DESTDIR}/static/images/${FILENAME}";
    echo "Moving ${SOURCEFILE} to ${WEBROOT}" >> $LOGFILE;
    mv "${SOURCEFILE}" "${WEBROOT}/images/";
    chmod 644 "${WEBROOT}/images/${FILENAME}";
    ;;
  md|markdown)
    echo "Moving ${SOURCEFILE} to ${DESTIR}" >> $LOGFILE;
    mv "${SOURCEFILE}" "${DESTDIR}/content/";
    chmod 644 "${DESTDIR}/content/${FILENAME}"
    echo "Generating site ${WEBROOT}";
    /usr/local/bin/hugo --quiet --config ${DESTDIR}/config.yaml -s ${DESTDIR} -d ${WEBROOT}
    ;;
  *)
    echo "Invalid file ${SOURCEFILE}.  I bail out." >> $LOGFILE;
    rm -f ${SOURCEFILE};
    exit 1;
    ;;
esac

#### TODO: optionally post to twitter
