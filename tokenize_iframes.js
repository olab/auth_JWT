(function()
{
  var scriptParams = new URL(document.currentScript.src).searchParams
    , hosts = scriptParams.get('hosts')
    , regexpr = atob(scriptParams.get('regexpr').trim())
    , token

  hosts = String(hosts).split(',').map(function(h)
  {
    return String(h).toLowerCase()
  }).filter(Boolean)

  if ( 0 == hosts.length )
    return

  new MutationObserver(() =>
  {
    document.querySelectorAll('#page-content iframe[src]:not(.__tokenized):not(.__ignore_frame)').forEach(function(frame)
    {
      var url = new URL(frame.src)

      try {
        if ( -1 == hosts.indexOf(url.hostname) )
          return frame.classList.add('__ignore_frame')
      } catch (err) {
        return
      }

      if ( token ) {
        url.searchParams.set('token', token)
      } else {
        url.searchParams.delete('token')
      }

      var copy = document.createElement('iframe')

      frame.getAttributeNames().forEach(function(name)
      {
        copy.setAttribute(name, frame.getAttribute(name))
      })

      copy.classList.add('__tokenized')
      copy.src = url.toString()

      if ( 'replaceWith' in frame ) {
        frame.replaceWith(copy)
      } else {
        frame.parentElement.appendChild(copy)
        frame.remove()
      }

      delete frame
    })
  }).observe(document, {
    subtree: true,
    childList: true,
  })

  setInterval(function()
  {
    var newToken = getTokenFromCookie()

    if ( token != newToken ) {
      document.querySelectorAll('#page-content iframe.__tokenized').forEach(function(frame)
      {
        frame.classList.remove('__tokenized')
      })

      document.body.appendChild(document.createTextNode(''))
    }

    token = newToken
  }, 1000)

  function getTokenFromCookie() {
    try {
      return document.cookie.match(new RegExp(regexpr))[2]
    } catch(err) {
      return ''
    }
  }

  token = getTokenFromCookie()
})()