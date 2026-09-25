// Home page questions and answers. Each answer is written to stand alone when
// an AI engine lifts it out, so it names postd.uk and avoids "we".
// Source: docs/marketing/aio-plan.md, Fix 8. Two claims the plan flagged as
// unverified were changed to match the code:
//   - Q5 no longer says your edits teach postd.uk your tone. Edited posts are
//     published as written but nothing feeds them back into generation.
//   - Q9 no longer promises "about ten minutes". Setup is timed by nobody yet,
//     so it describes the steps instead.
export const homeFaq = [
  {
    q: 'What is postd.uk?',
    a: 'postd.uk is a UK-built service that writes and publishes social media posts for small businesses automatically. It reads your website and Google reviews, writes posts in UK English, and publishes them to Google Business Profile, Facebook, LinkedIn Company Pages and X on a regular schedule.',
  },
  {
    q: 'How is postd.uk different from Buffer or Hootsuite?',
    a: 'Buffer and Hootsuite schedule posts you have already written; postd.uk writes the posts for you and then schedules them. For a small business owner, the difference is whether social media takes an hour a week or a few seconds to approve.',
  },
  {
    q: 'Can postd.uk post to Google Business Profile automatically?',
    a: 'Yes. postd.uk treats Google Business Profile as a main channel, writing short update posts from your services, offers and reviews and publishing them on a schedule, so your Google listing stays active without you logging in.',
  },
  {
    q: 'Where does postd.uk get the content for my posts?',
    a: 'postd.uk reads your own website (services, areas covered, what makes you different) and your real Google reviews. It turns that into varied posts, such as service spotlights, tips and customer quotes, rather than generic stock content.',
  },
  {
    q: 'Will the posts sound like my business?',
    a: 'You choose a tone during setup (professional, friendly or casual) and postd.uk writes every post in that voice using UK spelling, drawing on the details from your own website. You can edit any post before it goes out, and your edited wording is published exactly as you wrote it.',
  },
  {
    q: 'Do I have to approve every post?',
    a: 'No. By default each post waits in an approval queue so you can edit, approve or reject it with one tap. If you would rather not check, switch on auto-approval and postd.uk publishes on its own.',
  },
  {
    q: 'Which platforms does postd.uk support?',
    a: 'postd.uk publishes to Google Business Profile, Facebook Pages, LinkedIn Company Pages and X (Twitter). It does not post to Instagram, TikTok or personal LinkedIn profiles.',
  },
  {
    q: 'Who is postd.uk for?',
    a: 'postd.uk is for UK owner-run businesses that know they should post regularly but never find the time: trades such as plumbers, electricians and builders, plus salons, cafés, cleaners, garages and local professional services.',
  },
  {
    q: 'How long does it take to set up postd.uk?',
    a: 'Setup is a short five-step form. You sign in with Google, enter your business name, website and Google reviews link, and connect your accounts. As soon as you finish, postd.uk starts writing your first posts.',
  },
  {
    q: 'When does postd.uk publish posts?',
    a: 'postd.uk picks posting times for each platform based on when local audiences are active, in UK time, and never posts overnight. You can see and change every scheduled time from your post list.',
  },
  {
    q: 'Is postd.uk a UK company?',
    a: 'Yes. postd.uk is built and run by dijitul, a digital agency based in Mansfield, Nottinghamshire. It is designed for UK businesses, writes in UK English and bills in pounds sterling.',
  },
  {
    q: 'Can I use ChatGPT instead of postd.uk?',
    a: 'You can write posts with ChatGPT, but you still have to think of topics, paste in your details, copy each post into four platforms and remember to do it every week. postd.uk does all of that automatically from your own website and reviews, so posting keeps happening when you are busy.',
  },
]
